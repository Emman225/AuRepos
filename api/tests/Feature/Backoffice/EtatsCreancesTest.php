<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Partenaires\Models\CommissionApporteur;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function connecteAdminCreances(): void
{
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
});

it('donne l’encours d’un client à terme, plein s’il n’a rien réglé', function (): void {
    $client = User::factory()->create();
    Client::create(['user_id' => $client->id, 'statut_a_terme' => StatutDemandeATerme::Acceptee, 'plafond_credit' => 200000, 'nature' => 'b2b']);
    $logement = Logement::factory()->create();
    Sejour::factory()->create(['logement_id' => $logement->id, 'client_id' => $client->id, 'etat' => EtatDuSejour::Confirme, 'net_a_payer' => 75000]);

    connecteAdminCreances();

    test()->getJson('/api/v1/backoffice/etats/creances/a-terme')->assertOk()
        ->assertJsonPath('data.0.encours', 75000)
        ->assertJsonPath('data.0.plafond_credit', 200000)
        ->assertJsonPath('data.0.disponible', 125000);
});

it('classe le reste dû dans la bonne tranche d’ancienneté depuis le départ', function (): void {
    $logement = Logement::factory()->create();
    // Départ il y a 45 jours (aujourd'hui : 2026-11-10) → tranche 31-60.
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Cloture,
        'arrivee' => Carbon::parse('2026-09-24'), 'depart' => Carbon::parse('2026-09-26'), 'net_a_payer' => 40000,
    ]);

    connecteAdminCreances();

    test()->getJson('/api/v1/backoffice/etats/creances/balance-agee')->assertOk()
        ->assertJsonPath('data.total_reste_du', 40000)
        ->assertJsonPath('data.lignes.0.tranche', '31-60');
});

it('sépare le récapitulatif des créances entre comptant et à terme', function (): void {
    $clientATerme = User::factory()->create();
    Client::create(['user_id' => $clientATerme->id, 'statut_a_terme' => StatutDemandeATerme::Acceptee, 'plafond_credit' => 0, 'nature' => 'b2b']);
    $clientComptant = User::factory()->create();

    $logement = Logement::factory()->create();
    Sejour::factory()->create(['logement_id' => $logement->id, 'client_id' => $clientATerme->id, 'etat' => EtatDuSejour::Confirme, 'net_a_payer' => 30000]);
    Sejour::factory()->create(['logement_id' => $logement->id, 'client_id' => $clientComptant->id, 'etat' => EtatDuSejour::Confirme, 'net_a_payer' => 20000]);

    connecteAdminCreances();

    test()->getJson('/api/v1/backoffice/etats/creances/recapitulatif')->assertOk()
        ->assertJsonPath('data.total', 50000)
        ->assertJsonPath('data.a_terme', 30000)
        ->assertJsonPath('data.comptant', 20000);
});

it('additionne les commissions d’un apporteur sur la période, pour son état de paiement filleul', function (): void {
    $apporteur = Apporteur::factory()->create();
    $sejour = Sejour::factory()->create();
    $reglement1 = reglementEffectue([
        'sens' => 'encaissement', 'guichet' => 'sejours', 'agence_id' => Agence::factory()->create()->id,
        'tiers_id' => User::factory()->create()->id, 'montant' => 30000, 'mode' => 'especes', 'notes' => 'x',
        'saisi_par' => User::factory()->create()->id, 'saisi_le' => now(),
    ]);
    CommissionApporteur::create(['apporteur_id' => $apporteur->id, 'sejour_id' => $sejour->id, 'reglement_id' => $reglement1->id, 'montant' => 3000]);

    connecteAdminCreances();

    $reponse = test()->getJson('/api/v1/backoffice/etats/filleuls')->assertOk();
    $ligne = collect($reponse->json('data'))->firstWhere('code', $apporteur->code);

    expect($ligne['montant_commissions'])->toBe(3000)
        ->and($ligne['nombre_commissions'])->toBe(1);
});

it('liste en relance les comptes dont le reste dû dépasse le seuil, après le délai paramétré', function (): void {
    $logement = Logement::factory()->create();
    // Départ il y a 20 jours, reste dû 10000 : au-delà du seuil et du délai par défaut (15 jours, 5000 F).
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Cloture,
        'arrivee' => Carbon::parse('2026-10-19'), 'depart' => Carbon::parse('2026-10-21'), 'net_a_payer' => 10000,
    ]);
    // Reste dû sous le seuil : ne doit pas apparaître.
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Cloture,
        'arrivee' => Carbon::parse('2026-10-19'), 'depart' => Carbon::parse('2026-10-21'), 'net_a_payer' => 1000,
    ]);

    connecteAdminCreances();

    test()->getJson('/api/v1/backoffice/etats/relances')->assertOk()
        ->assertJsonPath('data.delai_jours', 15)
        ->assertJsonPath('data.seuil_montant', 5000)
        ->assertJsonCount(1, 'data.lignes')
        ->assertJsonPath('data.total', 10000);
});
