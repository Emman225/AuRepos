<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Referentiels\Models\Ville;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\BaremeTransfert;
use App\Domain\Transferts\Models\Chauffeur;
use App\Domain\Transferts\Models\Vehicule;
use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Mail\TransfertAffecteMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();

    $region = Region::create(['nom' => 'District autonome d’Abidjan']);
    $ville = Ville::create(['region_id' => $region->id, 'nom' => 'Abidjan']);
    $commune = Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);
    $type = TypeVehicule::create(['nom' => 'Berline', 'capacite' => 4]);
    BaremeTransfert::factory()->create(['commune_id' => $commune->id, 'type_vehicule_id' => $type->id, 'prix' => 15000]);

    $this->client = User::factory()->create();
    $this->sejour = Sejour::factory()->create(['client_id' => $this->client->id]);
    $this->transfert = app(GestionDesTransferts::class)->demander($this->sejour, [
        'lieu_de_prise_en_charge' => 'Aéroport', 'commune_id' => $commune->id, 'type_vehicule_souhaite_id' => $type->id,
        'date_heure_prevue' => '2026-11-10 14:00:00', 'nombre_passagers' => 2,
    ]);
    $this->chauffeur = Chauffeur::factory()->create();
    $this->vehicule = Vehicule::factory()->create(['chauffeur_id' => $this->chauffeur->id]);
    $this->autreChauffeur = Chauffeur::factory()->create();
});

function connecterPourTransfert(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

function codePriseEnChargeRecu(): string
{
    $code = null;
    Mail::assertQueued(TransfertAffecteMail::class, function (TransfertAffecteMail $m) use (&$code) {
        $code = $m->code;

        return true;
    });

    return (string) $code;
}

// ---------------------------------------------------------------- affectation

it('affecte un chauffeur et un véhicule, génère le code de prise en charge et notifie le client', function (): void {
    connecterPourTransfert(User::factory()->profil(Profil::Gestionnaire)->create());

    $reponse = test()->postJson("/api/v1/backoffice/transferts/{$this->transfert->id}/affecter", [
        'chauffeur_id' => $this->chauffeur->id, 'vehicule_id' => $this->vehicule->id, 'montant_verse_au_chauffeur' => 10000,
    ])->assertOk();

    expect($reponse->json('data.etat'))->toBe('affecte')
        ->and($reponse->json('data.code_prise_en_charge_emis'))->toBeTrue()
        // Jamais le code en clair côté back office.
        ->and($reponse->getContent())->not->toContain((string) codePriseEnChargeRecu());

    expect($this->transfert->refresh()->montant_verse_au_chauffeur)->toBe(10000);
    Mail::assertQueued(TransfertAffecteMail::class, fn ($m) => $m->hasTo($this->client->email));
});

it('refuse d’affecter un véhicule qui n’appartient pas au chauffeur désigné', function (): void {
    connecterPourTransfert(User::factory()->profil(Profil::Administrateur)->create());
    $vehiculeDeLAutre = Vehicule::factory()->create(['chauffeur_id' => $this->autreChauffeur->id]);

    test()->postJson("/api/v1/backoffice/transferts/{$this->transfert->id}/affecter", [
        'chauffeur_id' => $this->chauffeur->id, 'vehicule_id' => $vehiculeDeLAutre->id, 'montant_verse_au_chauffeur' => 10000,
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'vehicule_invalide');
});

it('refuse une seconde affectation d’un transfert déjà affecté', function (): void {
    connecterPourTransfert(User::factory()->profil(Profil::Administrateur)->create());
    app(GestionDesTransferts::class)->affecter($this->transfert, $this->chauffeur, $this->vehicule, 10000);

    test()->postJson("/api/v1/backoffice/transferts/{$this->transfert->id}/affecter", [
        'chauffeur_id' => $this->chauffeur->id, 'vehicule_id' => $this->vehicule->id, 'montant_verse_au_chauffeur' => 10000,
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'affectation_impossible');
});

// ---------------------------------------------------------------- clôture par code (chauffeur)

it('le chauffeur affecté clôture le transfert avec le bon code', function (): void {
    app(GestionDesTransferts::class)->affecter($this->transfert, $this->chauffeur, $this->vehicule, 10000);
    $code = codePriseEnChargeRecu();

    connecterPourTransfert($this->chauffeur->utilisateur);
    test()->postJson("/api/v1/chauffeur/transferts/{$this->transfert->id}/cloturer", ['code' => $code])
        ->assertOk()->assertJsonPath('data.etat', 'termine');

    expect($this->transfert->refresh()->etat->value)->toBe('termine');
});

it('un autre chauffeur ne peut pas clôturer un transfert qui ne lui est pas affecté (404)', function (): void {
    app(GestionDesTransferts::class)->affecter($this->transfert, $this->chauffeur, $this->vehicule, 10000);
    $code = codePriseEnChargeRecu();

    connecterPourTransfert($this->autreChauffeur->utilisateur);
    test()->postJson("/api/v1/chauffeur/transferts/{$this->transfert->id}/cloturer", ['code' => $code])->assertNotFound();
});

it('un code faux est refusé, et le code se verrouille après cinq essais', function (): void {
    app(GestionDesTransferts::class)->affecter($this->transfert, $this->chauffeur, $this->vehicule, 10000);
    connecterPourTransfert($this->chauffeur->utilisateur);

    for ($i = 1; $i <= 4; $i++) {
        test()->postJson("/api/v1/chauffeur/transferts/{$this->transfert->id}/cloturer", ['code' => '000000'])
            ->assertStatus(422)->assertJsonPath('errors.code.0', 'code_incorrect');
    }

    // Cinquième essai faux : verrouillage.
    test()->postJson("/api/v1/chauffeur/transferts/{$this->transfert->id}/cloturer", ['code' => '000000'])
        ->assertStatus(423)->assertJsonPath('errors.code.0', 'code_verrouille');

    expect($this->transfert->refresh()->etat->value)->toBe('affecte'); // toujours pas clôturé
});

// ---------------------------------------------------------------- annulation

it('annule un transfert en attente', function (): void {
    connecterPourTransfert(User::factory()->profil(Profil::Gestionnaire)->create());

    test()->postJson("/api/v1/backoffice/transferts/{$this->transfert->id}/annuler")
        ->assertOk()->assertJsonPath('data.etat', 'annule');
});

it('ferme le back office des transferts aux profils hors exploitation', function (): void {
    connecterPourTransfert(User::factory()->profil(Profil::Client)->create());

    test()->getJson('/api/v1/backoffice/transferts')->assertForbidden();
    test()->postJson("/api/v1/backoffice/transferts/{$this->transfert->id}/annuler")->assertForbidden();
});
