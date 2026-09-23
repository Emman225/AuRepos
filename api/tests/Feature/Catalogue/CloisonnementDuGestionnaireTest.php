<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Services\ReservationDeSejour;
use Database\Factories\ResidenceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->residenceA = Residence::factory()->create(['nom' => 'Résidence A']);
    $this->residenceB = Residence::factory()->create(['nom' => 'Résidence B']);
    $this->logementA = Logement::factory()->create(['residence_id' => $this->residenceA->id]);
    $this->logementB = Logement::factory()->create(['residence_id' => $this->residenceB->id]);

    $this->gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    $this->gestionnaire->residences()->attach($this->residenceA->id);
    test()->withToken(auth('api')->login($this->gestionnaire));
});

// ---------------------------------------------------------------- catalogue

it('liste seulement les résidences du gestionnaire, jamais les autres', function (): void {
    $reponse = test()->getJson('/api/v1/backoffice/residences')->assertOk();

    expect(array_column($reponse->json('data.elements'), 'id'))->toBe([$this->residenceA->id]);
});

it('affiche la résidence rattachée, et 404 — jamais 403 — sur une autre', function (): void {
    test()->getJson("/api/v1/backoffice/residences/{$this->residenceA->id}")->assertOk();
    test()->getJson("/api/v1/backoffice/residences/{$this->residenceB->id}")->assertNotFound();
});

it('refuse de modifier ou de supprimer une résidence hors de son périmètre', function (): void {
    test()->putJson("/api/v1/backoffice/residences/{$this->residenceB->id}", ['nom' => 'Renommée'])->assertNotFound();
    test()->deleteJson("/api/v1/backoffice/residences/{$this->residenceB->id}")->assertNotFound();
    expect($this->residenceB->refresh()->nom)->toBe('Résidence B');
});

it('atteint les logements de sa résidence, jamais ceux de l’autre', function (): void {
    test()->getJson("/api/v1/backoffice/residences/{$this->residenceA->id}/logements/{$this->logementA->id}")->assertOk();
    // La résidence B elle-même est invisible : la route entière répond 404, avant même de parler du logement.
    test()->getJson("/api/v1/backoffice/residences/{$this->residenceB->id}/logements/{$this->logementB->id}")->assertNotFound();
});

it('rattache automatiquement au créateur la résidence qu’il vient de créer', function (): void {
    $reponse = test()->postJson('/api/v1/backoffice/residences', [
        'proprietaire_id' => Proprietaire::factory()->create()->id,
        'quartier_id' => ResidenceFactory::unQuartier()->id,
        'nom' => 'Nouvelle résidence du gestionnaire',
    ])->assertCreated();

    $id = $reponse->json('data.id');
    test()->getJson("/api/v1/backoffice/residences/{$id}")->assertOk();
});

// ---------------------------------------------------------------- séjours

it('ne liste, dans les séjours, que ceux de ses résidences', function (): void {
    $client = User::factory()->create();
    $this->logementA->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();
    $this->logementB->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();

    $sejourA = app(ReservationDeSejour::class)->reserver($client, $this->logementA->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);
    app(ReservationDeSejour::class)->reserver($client, $this->logementB->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/sejours')->assertOk();

    expect(array_column($reponse->json('data.elements'), 'reference'))->toBe([$sejourA->reference]);
});

it('refuse d’afficher ou de confirmer un séjour hors de son périmètre, avec 404', function (): void {
    $client = User::factory()->create();
    $this->logementB->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000])->save();
    $sejourB = app(ReservationDeSejour::class)->reserver($client, $this->logementB->refresh(), [
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 1, 'mode_reglement' => 'agence',
    ]);

    test()->getJson("/api/v1/backoffice/sejours/{$sejourB->id}")->assertNotFound();
    test()->postJson("/api/v1/backoffice/sejours/{$sejourB->id}/confirmation")->assertNotFound();
});

// ---------------------------------------------------------------- comptes non restreints

it('ne restreint ni l’administrateur ni le super administrateur', function (Profil $profil): void {
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil($profil)->create()));

    $reponse = test()->getJson('/api/v1/backoffice/residences')->assertOk();

    expect(array_column($reponse->json('data.elements'), 'id'))->toContain($this->residenceA->id, $this->residenceB->id);
    test()->getJson("/api/v1/backoffice/residences/{$this->residenceB->id}")->assertOk();
})->with([[Profil::Administrateur], [Profil::SuperAdministrateur]]);

it('un gestionnaire sans résidence rattachée ne voit rien, pas tout', function (): void {
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $sansResidence = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($sansResidence));

    $reponse = test()->getJson('/api/v1/backoffice/residences')->assertOk();

    expect($reponse->json('data.elements'))->toBe([]);
    test()->getJson("/api/v1/backoffice/residences/{$this->residenceA->id}")->assertNotFound();
});
