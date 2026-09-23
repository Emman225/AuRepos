<?php

use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Extras\Models\Extra;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\Transfert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

/**
 * Guichets d'encaissement Extras et Transferts (P2-TRF-03) : ce qui reste dû sur les
 * consommations demandées PENDANT un séjour, imputé sur la consommation elle-même (jamais le
 * séjour), même circuit de preuve à quatre étapes que la caisse des séjours.
 */
beforeEach(function (): void {
    $this->agence = Agence::factory()->create();
    $personnel = fn (Profil $p) => User::factory()->profil($p)->create(['agence_id' => $this->agence->id]);
    $this->caissier = $personnel(Profil::Gestionnaire);
    [$this->admin1, $this->admin2] = [$personnel(Profil::Administrateur), $personnel(Profil::Administrateur)];
    $this->client = User::factory()->create();
    $this->sejour = Sejour::factory()->create(['client_id' => $this->client->id]);
});

function enGuichet(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** Déroule le circuit complet avec deux administrateurs (le second joint la preuve ET finalise). */
function auBoutDuGuichet(int $id): void
{
    enGuichet(test()->admin1);
    test()->putJson("/api/v1/backoffice/caisse/reglements/{$id}/validation")->assertOk();
    enGuichet(test()->admin2);
    test()->post("/api/v1/backoffice/caisse/reglements/{$id}/preuve", [
        'justificatif' => UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertOk();
    test()->putJson("/api/v1/backoffice/caisse/reglements/{$id}/finalisation")->assertOk();
}

// ---------------------------------------------------------------- Transferts

it('liste au guichet les transferts non annulés avec un reste dû', function (): void {
    $transfert = Transfert::factory()->create(['sejour_id' => $this->sejour->id, 'montant' => 15000]);
    Transfert::factory()->create(['sejour_id' => $this->sejour->id, 'montant' => 5000, 'etat' => 'annule']);

    enGuichet($this->caissier);
    $reponse = test()->getJson('/api/v1/backoffice/caisse/guichets/transferts')->assertOk();

    expect($reponse->json('data'))->toHaveCount(1)
        ->and($reponse->json('data.0.id'))->toBe($transfert->id)
        ->and($reponse->json('data.0.reste_du'))->toBe(15000)
        ->and($reponse->json('data.0.client.id'))->toBe($this->client->id);
});

it('encaisse un transfert au guichet, jusqu’au bout du circuit, avec le reçu RC-TR', function (): void {
    $transfert = Transfert::factory()->create(['sejour_id' => $this->sejour->id, 'montant' => 15000]);

    enGuichet($this->caissier);
    $id = test()->postJson("/api/v1/backoffice/caisse/guichets/transferts/{$transfert->id}/encaissement", [
        'montant' => 15000, 'mode' => 'especes', 'notes' => 'Encaissé au guichet transferts',
    ])->assertCreated()->assertJsonPath('data.guichet', 'transferts')->json('data.id');

    auBoutDuGuichet($id);

    $reglement = Reglement::findOrFail($id);
    expect($reglement->numero_recu)->toStartWith('RC-TR-')
        ->and($reglement->imputations()->first()->affaire_id)->toBe($transfert->id)
        ->and($reglement->imputations()->first()->affaire_type)->toBe($transfert->getMorphClass());

    // Soldé : il sort de la file du guichet.
    expect(test()->getJson('/api/v1/backoffice/caisse/guichets/transferts')->json('data'))->toHaveCount(0);
});

it('refuse d’encaisser plus que le reste dû d’un transfert', function (): void {
    $transfert = Transfert::factory()->create(['sejour_id' => $this->sejour->id, 'montant' => 15000]);
    enGuichet($this->caissier);

    test()->postJson("/api/v1/backoffice/caisse/guichets/transferts/{$transfert->id}/encaissement", [
        'montant' => 20000, 'mode' => 'especes', 'notes' => 'Trop perçu',
    ])->assertStatus(422)->assertJsonPath('errors.code.0', 'montant_superieur_au_reste_du');
});

it('accepte deux encaissements partiels d’un même transfert, jusqu’à solder son reste dû', function (): void {
    $transfert = Transfert::factory()->create(['sejour_id' => $this->sejour->id, 'montant' => 15000]);
    enGuichet($this->caissier);

    $id1 = test()->postJson("/api/v1/backoffice/caisse/guichets/transferts/{$transfert->id}/encaissement", [
        'montant' => 10000, 'mode' => 'especes', 'notes' => 'Première tranche',
    ])->assertCreated()->json('data.id');
    auBoutDuGuichet($id1);

    $reponse = test()->getJson('/api/v1/backoffice/caisse/guichets/transferts')->assertOk();
    expect($reponse->json('data.0.reste_du'))->toBe(5000);

    test()->postJson("/api/v1/backoffice/caisse/guichets/transferts/{$transfert->id}/encaissement", [
        'montant' => 5000, 'mode' => 'especes', 'notes' => 'Solde',
    ])->assertCreated();
});

// ---------------------------------------------------------------- Extras

it('liste au guichet les commandes d’extras non mortes avec un reste dû', function (): void {
    $extra = Extra::factory()->create(['prix' => 10000]);
    $commande = CommandeExtra::factory()->create(['sejour_id' => $this->sejour->id, 'extra_id' => $extra->id, 'montant_total' => 10000]);
    CommandeExtra::factory()->create(['sejour_id' => $this->sejour->id, 'extra_id' => $extra->id, 'montant_total' => 4000, 'etat' => 'refusee']);

    enGuichet($this->caissier);
    $reponse = test()->getJson('/api/v1/backoffice/caisse/guichets/extras')->assertOk();

    expect($reponse->json('data'))->toHaveCount(1)->and($reponse->json('data.0.id'))->toBe($commande->id);
});

it('encaisse une commande d’extra au guichet, avec le reçu RC-EX', function (): void {
    $extra = Extra::factory()->create(['prix' => 10000]);
    $commande = CommandeExtra::factory()->create(['sejour_id' => $this->sejour->id, 'extra_id' => $extra->id, 'montant_total' => 10000]);

    enGuichet($this->caissier);
    $id = test()->postJson("/api/v1/backoffice/caisse/guichets/extras/{$commande->id}/encaissement", [
        'montant' => 10000, 'mode' => 'mobile_money', 'notes' => 'Encaissé au guichet extras',
    ])->assertCreated()->assertJsonPath('data.guichet', 'extras')->json('data.id');

    auBoutDuGuichet($id);

    expect(Reglement::findOrFail($id)->numero_recu)->toStartWith('RC-EX-');
});

it('ferme les guichets aux profils hors exploitation', function (): void {
    enGuichet(User::factory()->profil(Profil::Client)->create());

    test()->getJson('/api/v1/backoffice/caisse/guichets/transferts')->assertForbidden();
    test()->getJson('/api/v1/backoffice/caisse/guichets/extras')->assertForbidden();
});
