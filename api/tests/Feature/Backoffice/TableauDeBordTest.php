<?php

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Devis;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $this->residenceA = Residence::factory()->create();
    $this->residenceB = Residence::factory()->create();
    $this->logementA = Logement::factory()->create(['residence_id' => $this->residenceA->id]);
    $this->logementB = Logement::factory()->create(['residence_id' => $this->residenceB->id]);
});

/** Sème un jeu de séjours et de devis répartis dans tous les compteurs, sur les DEUX résidences. */
function semerLesCompteurs(): void
{
    $residences = [test()->logementA, test()->logementB];

    foreach ($residences as $logement) {
        Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Demande]);
        Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-10', 'depart' => '2026-11-13']);
        // Arrivée future, confirmée : ne doit PAS compter dans « arrivées du jour ».
        Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Confirme, 'arrivee' => '2026-11-15', 'depart' => '2026-11-18']);
        Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Arrive, 'arrivee' => '2026-11-08', 'depart' => '2026-11-10']);
        Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Arrive, 'arrivee' => '2026-11-09', 'depart' => '2026-11-12']);
        Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Cloture]);
        Sejour::factory()->create(['logement_id' => $logement->id, 'etat' => EtatDuSejour::Annule]);
        creerUnDevis($logement->id, 'en_attente');
        creerUnDevis($logement->id, 'transforme');
    }
}

function creerUnDevis(int $logementId, string $etat): Devis
{
    return Devis::create([
        'logement_id' => $logementId, 'client_id' => User::factory()->create()->id,
        'arrivee' => '2026-11-20', 'depart' => '2026-11-23', 'devis' => [], 'net_a_payer' => 50000, 'caution' => 20000, 'etat' => $etat,
    ]);
}

it('compte, pour un administrateur, sur les DEUX résidences', function (): void {
    semerLesCompteurs();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Administrateur)->create()));

    test()->getJson('/api/v1/backoffice/tableau-de-bord')->assertOk()
        ->assertJsonPath('data.reservations_en_attente', 2)
        ->assertJsonPath('data.arrivees_du_jour', 2)
        ->assertJsonPath('data.departs_du_jour', 2)
        ->assertJsonPath('data.sejours_en_cours', 4)
        ->assertJsonPath('data.devis_en_attente', 2);
});

it('ne compte, pour un gestionnaire, que sa résidence (CdC § 9.5)', function (): void {
    semerLesCompteurs();
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    $gestionnaire->residences()->attach($this->residenceA->id);
    test()->withToken(auth('api')->login($gestionnaire));

    test()->getJson('/api/v1/backoffice/tableau-de-bord')->assertOk()
        ->assertJsonPath('data.reservations_en_attente', 1)
        ->assertJsonPath('data.arrivees_du_jour', 1)
        ->assertJsonPath('data.departs_du_jour', 1)
        ->assertJsonPath('data.sejours_en_cours', 2)
        ->assertJsonPath('data.devis_en_attente', 1);
});

it('un gestionnaire sans résidence rattachée voit des compteurs à zéro, pas tout', function (): void {
    semerLesCompteurs();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Gestionnaire)->create()));

    test()->getJson('/api/v1/backoffice/tableau-de-bord')->assertOk()
        ->assertJsonPath('data.reservations_en_attente', 0)
        ->assertJsonPath('data.sejours_en_cours', 0)
        ->assertJsonPath('data.devis_en_attente', 0);
});

it('refuse l’accès à un profil hors exploitation', function (): void {
    test()->withToken(auth('api')->login(User::factory()->create()));

    test()->getJson('/api/v1/backoffice/tableau-de-bord')->assertForbidden();
});
