<?php

use App\Domain\Comptes\Models\User;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Referentiels\Models\Ville;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\BaremeTransfert;
use App\Domain\Transferts\Models\Transfert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-01 10:00:00');

    $region = Region::create(['nom' => 'District autonome d’Abidjan']);
    $ville = Ville::create(['region_id' => $region->id, 'nom' => 'Abidjan']);
    $this->commune = Commune::create(['ville_id' => $ville->id, 'nom' => 'Cocody']);
    $this->autreCommune = Commune::create(['ville_id' => $ville->id, 'nom' => 'Marcory']);
    $this->typeVehicule = TypeVehicule::create(['nom' => 'Berline', 'capacite' => 4]);

    $this->bareme = BaremeTransfert::factory()->create([
        'commune_id' => $this->commune->id, 'type_vehicule_id' => $this->typeVehicule->id, 'prix' => 15000,
    ]);

    $this->client = User::factory()->create();
    $this->sejour = Sejour::factory()->create(['client_id' => $this->client->id]);
});

function connecterCommeLeClientDuTransfert(): void
{
    test()->withToken(auth('api')->login(test()->client));
}

function donneesDeDemande(array $surcharge = []): array
{
    $t = test();

    return array_merge([
        'lieu_de_prise_en_charge' => 'Aéroport Félix-Houphouët-Boigny',
        'commune_id' => $t->commune->id,
        'type_vehicule_souhaite_id' => $t->typeVehicule->id,
        'date_heure_prevue' => '2026-11-10 14:00:00',
        'nombre_passagers' => 2,
        'nombre_bagages' => 1,
    ], $surcharge);
}

// ---------------------------------------------------------------- calcul depuis le barème

it('calcule le montant depuis le barème (zone × type de véhicule)', function (): void {
    connecterCommeLeClientDuTransfert();
    $reponse = test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts", donneesDeDemande())
        ->assertCreated();

    expect($reponse->json('data.montant'))->toBe(15000)
        ->and($reponse->json('data.etat'))->toBe('demande');

    $transfert = Transfert::first();
    expect($transfert->montant)->toBe(15000)
        ->and($transfert->etat->value)->toBe('demande')
        ->and($transfert->sejour_id)->toBe($this->sejour->id);
});

it('refuse la demande avec un montant fourni par le client : le montant vient toujours du barème', function (): void {
    connecterCommeLeClientDuTransfert();
    // Même si un « montant » est envoyé dans le corps, il est ignoré : seul le barème compte.
    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts", donneesDeDemande(['montant' => 1]))
        ->assertCreated();

    expect(Transfert::first()->montant)->toBe(15000);
});

it('refuse avec une erreur explicite (422) quand aucun barème ne correspond à la zone et au véhicule', function (): void {
    connecterCommeLeClientDuTransfert();
    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts", donneesDeDemande(['commune_id' => $this->autreCommune->id]))
        ->assertStatus(422)
        ->assertJsonPath('errors.code.0', 'bareme_introuvable');

    expect(Transfert::count())->toBe(0);
});

// ---------------------------------------------------------------- isolation

it('refuse la demande de transfert sur le séjour d’un autre client (404, jamais 403)', function (): void {
    $autreClient = User::factory()->create();
    test()->withToken(auth('api')->login($autreClient));

    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts", donneesDeDemande())->assertNotFound();
    test()->getJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts")->assertNotFound();
});

it('liste MES transferts sur ce séjour, avec le code de prise en charge en clair s’il est émis', function (): void {
    connecterCommeLeClientDuTransfert();
    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts", donneesDeDemande())->assertCreated();

    $reponse = test()->getJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts")->assertOk();

    expect($reponse->json('data'))->toHaveCount(1)
        ->and($reponse->json('data.0.montant'))->toBe(15000)
        ->and($reponse->json('data.0.etat'))->toBe('demande')
        // Pas encore affecté : aucun code n'existe encore.
        ->and($reponse->json('data.0.code_prise_en_charge'))->toBeNull();
});

it('refuse l’accès sans authentification', function (): void {
    test()->postJson("/api/v1/client/sejours/{$this->sejour->reference}/transferts", donneesDeDemande())->assertUnauthorized();
});
