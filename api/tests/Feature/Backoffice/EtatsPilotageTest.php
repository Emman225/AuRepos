<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function devisPilotage(int $hebergementNetHt, int $totalTva = 0, int $tdt = 0, int $taxeDeSejour = 0): array
{
    return [
        'hebergement_net_ht' => $hebergementNetHt, 'extras_ht' => 0, 'transfert_ht' => 0,
        'total_ht' => $hebergementNetHt, 'total_tva' => $totalTva, 'tdt' => $tdt,
        'taxe_de_sejour' => $taxeDeSejour, 'autres_taxes' => $tdt + $taxeDeSejour,
    ];
}

function connecteAdmin(): void
{
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
});

it('additionne le chiffre d’affaires détaillé sur la période, en excluant les demandes et les annulés', function (): void {
    $residence = Residence::factory()->create(['nom' => 'Résidence Test']);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);

    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-11-05', 'depart' => '2026-11-08',
        'devis' => devisPilotage(90000, 16200, 900, 3000), 'net_a_payer' => 110100,
    ]);
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-11-06', 'depart' => '2026-11-09',
        'devis' => devisPilotage(60000, 10800, 600, 2000), 'net_a_payer' => 73400,
    ]);
    // Hors période : ne doit pas être compté.
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-12-20', 'depart' => '2026-12-23',
        'devis' => devisPilotage(50000), 'net_a_payer' => 50000,
    ]);
    // Demande (pas encore vendue) : ne doit pas être comptée.
    Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Demande, 'arrivee' => '2026-11-07', 'depart' => '2026-11-09']);

    connecteAdmin();

    test()->getJson('/api/v1/backoffice/etats/ca-detaille?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.totaux.total_ht', 150000)
        ->assertJsonPath('data.totaux.total_tva', 27000)
        ->assertJsonPath('data.totaux.autres_taxes', 6500)
        ->assertJsonPath('data.totaux.net_a_payer_sejours', 183500)
        ->assertJsonCount(2, 'data.lignes_sejours');
});

it('calcule occupation, RevPAR et prix moyen à partir des nuitées disponibles et vendues', function (): void {
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    // 3 nuitées vendues sur la période complète (3 jours), toutes dans un seul logement.
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-11-01', 'depart' => '2026-11-04',
        'devis' => devisPilotage(90000), 'net_a_payer' => 90000,
    ]);

    connecteAdmin();

    $reponse = test()->getJson('/api/v1/backoffice/etats/occupation?du=2026-11-01&au=2026-11-03&residence_id='.$residence->id)->assertOk();
    $ligne = $reponse->json('data.0');

    expect($ligne['nuitees_disponibles'])->toBe(3) // 1 logement × 3 jours
        ->and($ligne['nuitees_vendues'])->toBe(3)
        ->and($ligne['taux_occupation'])->toBe(100) // pourcentage 0-100 ; JSON ne distingue pas 100 de 100.0
        ->and($ligne['revpar'])->toBe(30000) // 90000 / 3
        ->and($ligne['prix_moyen'])->toBe(30000);
});

it('compte les annulations et somme le montant retenu, sur la période de l’annulation', function (): void {
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);

    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Annule,
        'annule_le' => '2026-11-05 10:00:00', 'montant_retenu_annulation' => 15000, 'motif_annulation' => 'Client',
    ]);
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Annule,
        'annule_le' => '2026-11-06 10:00:00', 'montant_retenu_annulation' => 5000, 'motif_annulation' => 'Client',
    ]);
    // Hors période.
    Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Annule, 'annule_le' => '2026-10-01 10:00:00', 'montant_retenu_annulation' => 99999]);

    connecteAdmin();

    test()->getJson('/api/v1/backoffice/etats/annulations?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.annulations.volume', 2)
        ->assertJsonPath('data.annulations.montant_retenu', 20000);
});

it('calcule la marge par résidence : prix de vente hébergement moins coût propriétaire', function (): void {
    $residence = Residence::factory()->create(['nom' => 'Résidence Marge']);
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);

    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme,
        'arrivee' => '2026-11-05', 'depart' => '2026-11-08', // 3 nuits
        'devis' => devisPilotage(90000), 'net_a_payer' => 90000, 'prix_proprietaire_par_nuit' => 20000,
    ]);

    connecteAdmin();

    test()->getJson('/api/v1/backoffice/etats/marge-par-residence?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.0.prix_de_vente_ht', 90000)
        ->assertJsonPath('data.0.cout_proprietaire', 60000) // 20000 × 3 nuits
        ->assertJsonPath('data.0.marge', 30000);
});

it('additionne le revenu attendu des séjours confirmés à venir sur 90 jours', function (): void {
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);

    Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-20', 'depart' => '2026-11-23', 'net_a_payer' => 50000]);
    Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Arrive, 'arrivee' => '2026-12-01', 'depart' => '2026-12-03', 'net_a_payer' => 30000]);
    // Au-delà de 90 jours : ne doit pas compter.
    Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'arrivee' => '2027-04-01', 'depart' => '2027-04-03', 'net_a_payer' => 999999]);

    connecteAdmin();

    test()->getJson('/api/v1/backoffice/etats/previsionnel')->assertOk()
        ->assertJsonPath('data.nombre_sejours', 2)
        ->assertJsonPath('data.revenu_attendu', 80000);
});
