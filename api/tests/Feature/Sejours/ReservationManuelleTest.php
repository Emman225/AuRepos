<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\BienvenuePartenaireMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    $this->logement = Logement::factory()->create();
    $this->logement->forceFill(['etat_publication' => EtatPublication::Publie, 'prix_vente' => 30000, 'prix_proprietaire' => 22000])->save();
    $this->receptionniste = User::factory()->profil(Profil::Administrateur)->create();
});

function connecteALaReception(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

/** @return array<string, mixed> */
function saisieManuelle(array $surcharge = []): array
{
    return [
        'canal' => 'telephone', 'reference_logement' => test()->logement->reference,
        'arrivee' => '2026-11-10', 'depart' => '2026-11-13', 'adultes' => 2, 'mode_reglement' => 'agence',
        ...$surcharge,
    ];
}

// ---------------------------------------------------------------- client existant ou nouveau

it('réserve pour un client déjà existant', function (): void {
    connecteALaReception($this->receptionniste);
    $client = User::factory()->create();

    $reponse = test()->postJson('/api/v1/backoffice/sejours', saisieManuelle(['client_id' => $client->id]))
        ->assertCreated();

    $sejour = Sejour::where('reference', $reponse->json('data.reference'))->firstOrFail();
    expect($sejour->client_id)->toBe($client->id)
        ->and($sejour->canal)->toBe('telephone')
        ->and($sejour->getAttribute('cree_par'))->toBe($this->receptionniste->id);
    Mail::assertNothingSent(); // aucun courriel de bienvenue : le client existait déjà
});

it('crée un compte pour un nouveau client, avec un mot de passe que personne ne connaît', function (): void {
    connecteALaReception($this->receptionniste);

    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle([
        'client' => ['nom' => 'Koné', 'prenoms' => 'Awa', 'email' => 'awa.reception@exemple.ci', 'telephone' => '+2250700112233'],
    ]))->assertCreated();

    $client = User::where('email', 'awa.reception@exemple.ci')->firstOrFail();
    expect($client->profil)->toBe(Profil::Client)
        ->and($client->email_verified_at)->not->toBeNull() // la réception l'a identifié en personne
        ->and($client->telephone)->toBe('+2250700112233');
    Mail::assertQueued(BienvenuePartenaireMail::class, fn ($m) => $m->hasTo('awa.reception@exemple.ci'));
});

it('ne duplique jamais un client déjà connu, retrouvé par courriel', function (): void {
    connecteALaReception($this->receptionniste);
    $existant = User::factory()->create(['email' => 'deja.la@exemple.ci']);

    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle([
        'client' => ['nom' => 'Peu importe', 'email' => 'DEJA.LA@exemple.ci'],
    ]))->assertCreated();

    expect(User::where('email', 'deja.la@exemple.ci')->count())->toBe(1);
    $sejour = Sejour::sole();
    expect($sejour->client_id)->toBe($existant->id);
});

it('refuse sans client existant ni nouveau, et refuse les deux à la fois', function (): void {
    connecteALaReception($this->receptionniste);

    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle())
        ->assertStatus(422)->assertJsonStructure(['errors' => ['client_id']]);

    $client = User::factory()->create();
    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle([
        'client_id' => $client->id, 'client' => ['nom' => 'X', 'email' => 'x@exemple.ci'],
    ]))->assertStatus(422)->assertJsonStructure(['errors' => ['client_id']]);
});

// ---------------------------------------------------------------- canaux

it('accepte les trois canaux de la réception', function (string $canal): void {
    connecteALaReception($this->receptionniste);
    $client = User::factory()->create();

    $reponse = test()->postJson('/api/v1/backoffice/sejours', saisieManuelle(['canal' => $canal, 'client_id' => $client->id]))
        ->assertCreated();

    expect(Sejour::where('reference', $reponse->json('data.reference'))->value('canal'))->toBe($canal);
})->with(['telephone', 'walk_in', 'canal_externe']);

it('refuse un canal inconnu', function (): void {
    connecteALaReception($this->receptionniste);
    $client = User::factory()->create();

    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle(['canal' => 'direct', 'client_id' => $client->id]))
        ->assertStatus(422)->assertJsonStructure(['errors' => ['canal']]);
});

// ---------------------------------------------------------------- cloisonnement du gestionnaire

it('refuse à un gestionnaire de réserver hors de ses résidences', function (): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    connecteALaReception($gestionnaire);
    $client = User::factory()->create();

    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle(['client_id' => $client->id]))
        ->assertNotFound();
});

it('laisse un gestionnaire réserver dans SA résidence', function (): void {
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    $gestionnaire->residences()->attach($this->logement->residence_id);
    connecteALaReception($gestionnaire);
    $client = User::factory()->create();

    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle(['client_id' => $client->id]))->assertCreated();
});

// ---------------------------------------------------------------- accès

it('ferme la réservation manuelle aux profils hors exploitation', function (): void {
    connecteALaReception(User::factory()->profil(Profil::Client)->create());

    test()->postJson('/api/v1/backoffice/sejours', saisieManuelle(['client_id' => User::factory()->create()->id]))
        ->assertForbidden();
});
