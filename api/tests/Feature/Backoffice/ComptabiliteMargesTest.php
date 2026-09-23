<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Caisse\Services\Cautions;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Enums\EtatDuTransfert;
use App\Domain\Transferts\Models\Transfert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
    Storage::fake('local');
});

function connecteAdminMarges(): void
{
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));
}

/**
 * Personnel de caisse d'une même agence : un caissier et trois administrateurs distincts, ce que
 * le circuit de preuve exige (saisie, validation, puis preuve et finalisation par un troisième).
 *
 * @return array{caissier: User, admins: list<User>}
 */
function equipeDeCaisseMarges(): array
{
    $agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $agence->id]);

    return [
        'caissier' => $personnel(Profil::Gestionnaire),
        'admins' => [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur), $personnel(Profil::Administrateur)],
    ];
}

/** Restitution réellement décaissée : saisie par un administrateur, puis circuit de preuve complet. */
function restituerLaCaution(Sejour $sejour, int $montant, array $admins): void
{
    $caisse = app(Caisse::class);
    $r = app(Cautions::class)->restituer($admins[2], $sejour->refresh(), $montant, ModeDeReglement::Especes, 'Restitution après état des lieux');
    $caisse->valider($r, $admins[0]);
    $caisse->joindreLaPreuve($r->refresh(), $admins[1], UploadedFile::fake()->create('recu-restitution.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $admins[1]);
}

it('calcule la marge par séjour : facturé HT moins reversé au propriétaire', function (): void {
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-05', 'depart' => '2026-11-08', // 3 nuits
        'devis' => ['total_ht' => 90000], 'net_a_payer' => 90000, 'prix_proprietaire_par_nuit' => 20000,
    ]);

    connecteAdminMarges();

    test()->getJson('/api/v1/backoffice/comptabilite/marges/sejours?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.lignes.0.facture_ht', 90000)
        ->assertJsonPath('data.lignes.0.reverse_proprietaire', 60000)
        ->assertJsonPath('data.total_marge', 30000);
});

it('calcule la marge par transfert : facturé moins versé au chauffeur', function (): void {
    $sejour = Sejour::factory()->create();
    Transfert::factory()->create([
        'sejour_id' => $sejour->id, 'etat' => EtatDuTransfert::Termine, 'date_heure_prevue' => '2026-11-05 10:00:00',
        'montant' => 15000, 'montant_verse_au_chauffeur' => 10000,
    ]);

    connecteAdminMarges();

    test()->getJson('/api/v1/backoffice/comptabilite/marges/transferts?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.total_marge', 5000);
});

it('additionne le bloc « Bénéfices de l’entreprise » sur séjours et transferts', function (): void {
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);
    $sejour = Sejour::factory()->create([
        'logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-05', 'depart' => '2026-11-06',
        'devis' => ['total_ht' => 20000], 'net_a_payer' => 20000, 'prix_proprietaire_par_nuit' => 5000,
    ]);
    Transfert::factory()->create([
        'sejour_id' => $sejour->id, 'etat' => EtatDuTransfert::Termine, 'date_heure_prevue' => '2026-11-05 10:00:00',
        'montant' => 8000, 'montant_verse_au_chauffeur' => 3000,
    ]);

    connecteAdminMarges();

    // Marge séjour : 20000 - 5000 = 15000. Marge transfert : 8000 - 3000 = 5000. Total : 20000.
    test()->getJson('/api/v1/backoffice/comptabilite/marges/recapitulatif?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.benefices_sejours', 15000)
        ->assertJsonPath('data.benefices_transferts', 5000)
        ->assertJsonPath('data.benefices_entreprise', 20000);
});

it('ne compte comme retenue ou restituée que ce qui a RÉELLEMENT bougé en caisse', function (): void {
    $residence = Residence::factory()->create();
    $logement = Logement::factory()->create(['residence_id' => $residence->id]);

    ['caissier' => $caissier, 'admins' => $admins] = equipeDeCaisseMarges();
    $client = User::factory()->create(); // le tiers du règlement de caution, jamais nul en caisse

    // Séjour clôturé, caution déposée puis 10 000 retenus et 40 000 restitués : soldé.
    $solde = Sejour::factory()->create([
        'logement_id' => $logement->id, 'client_id' => $client->id, 'etat' => EtatDuSejour::Cloture,
        'arrivee' => '2026-11-05', 'depart' => '2026-11-08',
        'caution' => 50000,
    ]);
    deposerEtFinaliserLaCaution($solde, $caissier, $admins[0], $admins[1]);
    app(Cautions::class)->retenir($admins[0], $solde->refresh(), 10000, 'Vaisselle cassée', [], $admins[0]);
    restituerLaCaution($solde, 40000, $admins);

    // Séjour clôturé lui aussi, MAIS dont la restitution n'a pas encore été décaissée : les
    // 30 000 restent détenus. L'ancienne version les déclarait restitués sur le seul état du
    // séjour — une dette envers le client effacée d'un trait, alors que l'argent est en caisse.
    $enAttenteDeRestitution = Sejour::factory()->create([
        'logement_id' => $logement->id, 'client_id' => $client->id, 'etat' => EtatDuSejour::Cloture,
        'arrivee' => '2026-11-05', 'depart' => '2026-11-12',
        'caution' => 30000,
    ]);
    deposerEtFinaliserLaCaution($enAttenteDeRestitution, $caissier, $admins[0], $admins[1]);

    connecteAdminMarges();

    test()->getJson('/api/v1/backoffice/comptabilite/cautions?du=2026-11-01&au=2026-11-30')->assertOk()
        ->assertJsonPath('data.totaux.caution', 80000)
        ->assertJsonPath('data.totaux.retenue', 10000)
        ->assertJsonPath('data.totaux.restituee', 40000)
        ->assertJsonPath('data.totaux.detenue', 30000);
});
