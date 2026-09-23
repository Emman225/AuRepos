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
use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Mail\BienvenuePartenaireMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();

    $region = Region::create(['nom' => 'District autonome d’Abidjan']);
    $ville = Ville::create(['region_id' => $region->id, 'nom' => 'Abidjan']);
    $this->commune = Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);
    $this->type = TypeVehicule::create(['nom' => 'Berline', 'capacite' => 4]);
});

function connecterBackofficeTransfert(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $u = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($u));

    return $u;
}

// ---------------------------------------------------------------- liste et filtre par état

it('liste les transferts et filtre par état', function (): void {
    connecterBackofficeTransfert(Profil::Gestionnaire);
    BaremeTransfert::factory()->create(['commune_id' => $this->commune->id, 'type_vehicule_id' => $this->type->id, 'prix' => 15000]);

    $gestion = app(GestionDesTransferts::class);
    $sejour1 = Sejour::factory()->create(['client_id' => User::factory()->create()->id]);
    $sejour2 = Sejour::factory()->create(['client_id' => User::factory()->create()->id]);
    $donnees = ['lieu_de_prise_en_charge' => 'Aéroport', 'commune_id' => $this->commune->id, 'type_vehicule_souhaite_id' => $this->type->id, 'date_heure_prevue' => '2026-11-10 14:00:00', 'nombre_passagers' => 2];
    $gestion->demander($sejour1, $donnees);
    $t2 = $gestion->demander($sejour2, $donnees);
    $gestion->annuler($t2);

    $tous = test()->getJson('/api/v1/backoffice/transferts')->assertOk()->json('data.elements');
    expect($tous)->toHaveCount(2);

    $demandes = test()->getJson('/api/v1/backoffice/transferts?etat=demande')->assertOk()->json('data.elements');
    expect($demandes)->toHaveCount(1);

    $annules = test()->getJson('/api/v1/backoffice/transferts?etat=annule')->assertOk()->json('data.elements');
    expect($annules)->toHaveCount(1);
});

// ---------------------------------------------------------------- barème (réservé administrateur)

it('crée, modifie et supprime un barème de transfert (administrateur seulement)', function (): void {
    connecterBackofficeTransfert(Profil::Administrateur);

    $reponse = test()->postJson('/api/v1/backoffice/baremes-transfert', [
        'commune_id' => $this->commune->id, 'type_vehicule_id' => $this->type->id, 'prix' => 12000,
    ])->assertCreated();
    $id = $reponse->json('data.id');

    test()->putJson("/api/v1/backoffice/baremes-transfert/{$id}", ['prix' => 13000])
        ->assertOk()->assertJsonPath('data.prix', 13000);

    test()->getJson('/api/v1/backoffice/baremes-transfert')->assertOk()->assertJsonCount(1, 'data');

    test()->deleteJson("/api/v1/backoffice/baremes-transfert/{$id}")->assertOk();
    test()->getJson('/api/v1/backoffice/baremes-transfert')->assertOk()->assertJsonCount(0, 'data');
});

it('refuse un doublon de barème pour la même zone et le même type de véhicule', function (): void {
    connecterBackofficeTransfert(Profil::Administrateur);
    BaremeTransfert::factory()->create(['commune_id' => $this->commune->id, 'type_vehicule_id' => $this->type->id, 'prix' => 12000]);

    test()->postJson('/api/v1/backoffice/baremes-transfert', [
        'commune_id' => $this->commune->id, 'type_vehicule_id' => $this->type->id, 'prix' => 15000,
    ])->assertStatus(422);
});

it('ferme le barème des transferts à un gestionnaire (réservé administrateur, comme la grille tarifaire)', function (): void {
    connecterBackofficeTransfert(Profil::Gestionnaire);

    test()->getJson('/api/v1/backoffice/baremes-transfert')->assertForbidden();
    test()->postJson('/api/v1/backoffice/baremes-transfert', ['commune_id' => $this->commune->id, 'type_vehicule_id' => $this->type->id, 'prix' => 1])->assertForbidden();
});

// ---------------------------------------------------------------- chauffeurs et véhicules

it('crée un chauffeur, lui rattache un véhicule et le liste', function (): void {
    connecterBackofficeTransfert(Profil::Gestionnaire);

    $reponse = test()->postJson('/api/v1/backoffice/chauffeurs', [
        'nom' => 'Kone', 'prenoms' => 'Issa', 'email' => 'issa@exemple.ci',
    ])->assertCreated();
    $chauffeurId = $reponse->json('data.id');

    Mail::assertQueued(BienvenuePartenaireMail::class, fn ($m) => $m->hasTo('issa@exemple.ci'));

    $vehicule = test()->postJson("/api/v1/backoffice/chauffeurs/{$chauffeurId}/vehicules", [
        'type_vehicule_id' => $this->type->id, 'immatriculation' => 'CI-9999-ZZ',
    ])->assertCreated();

    expect($vehicule->json('data.chauffeur_id'))->toBe($chauffeurId);
    test()->getJson("/api/v1/backoffice/chauffeurs/{$chauffeurId}/vehicules")->assertOk()->assertJsonCount(1, 'data');
});

it('désactive un chauffeur', function (): void {
    connecterBackofficeTransfert(Profil::Administrateur);
    $chauffeur = Chauffeur::factory()->create(['actif' => true]);

    test()->putJson("/api/v1/backoffice/chauffeurs/{$chauffeur->id}", ['actif' => false])
        ->assertOk()->assertJsonPath('data.actif', false);

    expect($chauffeur->refresh()->actif)->toBeFalse();
});

it('ferme les chauffeurs aux profils hors exploitation', function (): void {
    connecterBackofficeTransfert(Profil::Client);

    test()->getJson('/api/v1/backoffice/chauffeurs')->assertForbidden();
});
