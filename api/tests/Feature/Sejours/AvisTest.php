<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\StatutAvis;
use App\Domain\Sejours\Models\Avis;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterUnClientPourAvis(): User
{
    $client = User::factory()->create();
    test()->withToken(auth('api')->login($client));

    return $client;
}

function connecterUnAdministrateurPourAvis(): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $administrateur = User::factory()->profil(Profil::Administrateur)->create();
    test()->withToken(auth('api')->login($administrateur));

    return $administrateur;
}

// ---------------------------------------------------------------- dépôt (client)

it('dépose un avis sur son propre séjour clôturé', function (): void {
    $client = connecterUnClientPourAvis();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Cloture]);

    $reponse = test()->postJson("/api/v1/client/sejours/{$sejour->reference}/avis", ['note' => 5, 'commentaire' => 'Séjour parfait.'])
        ->assertCreated();

    expect($reponse->json('data.statut'))->toBe('en_attente')
        ->and(Avis::sole()->note)->toBe(5);
});

it('refuse un avis avant la clôture du séjour', function (): void {
    $client = connecterUnClientPourAvis();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Confirme]);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/avis", ['note' => 5])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'sejour_non_termine');
});

it('refuse un second avis sur le même séjour', function (): void {
    $client = connecterUnClientPourAvis();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Cloture]);
    Avis::create(['sejour_id' => $sejour->id, 'note' => 4]);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/avis", ['note' => 5])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'avis_deja_soumis');
});

it('répond 404 — jamais 403 — sur le séjour d’un autre client', function (): void {
    connecterUnClientPourAvis();
    $sejour = Sejour::factory()->create(['etat' => EtatDuSejour::Cloture]);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/avis", ['note' => 5])->assertNotFound();
});

it('refuse une note hors de 1 à 5', function (): void {
    $client = connecterUnClientPourAvis();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Cloture]);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/avis", ['note' => 6])
        ->assertStatus(422)->assertJsonValidationErrors('note');
});

// ---------------------------------------------------------------- modération (back office)

it('liste la file des avis en attente et les publie ou les refuse', function (): void {
    $client = User::factory()->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Cloture]);
    $avis = Avis::create(['sejour_id' => $sejour->id, 'note' => 4, 'commentaire' => 'Très bien.']);

    connecterUnAdministrateurPourAvis();

    test()->getJson('/api/v1/backoffice/avis')->assertOk()->assertJsonCount(1, 'data.elements');

    test()->putJson("/api/v1/backoffice/avis/{$avis->id}/decision", ['decision' => 'publier'])
        ->assertOk()->assertJsonPath('data.statut', 'publie');

    expect($avis->refresh()->statut)->toBe(StatutAvis::Publie)
        ->and($avis->modere_par)->not->toBeNull();
});

it('exige un motif pour refuser un avis', function (): void {
    $sejour = Sejour::factory()->create(['etat' => EtatDuSejour::Cloture]);
    $avis = Avis::create(['sejour_id' => $sejour->id, 'note' => 1, 'commentaire' => 'Décevant.']);

    connecterUnAdministrateurPourAvis();

    test()->putJson("/api/v1/backoffice/avis/{$avis->id}/decision", ['decision' => 'refuser'])
        ->assertStatus(422)->assertJsonValidationErrors('motif');

    test()->putJson("/api/v1/backoffice/avis/{$avis->id}/decision", ['decision' => 'refuser', 'motif' => 'Langage inapproprié.'])
        ->assertOk()->assertJsonPath('data.statut', 'refuse');
});

it('exporte la liste des avis en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    $sejour = Sejour::factory()->create(['etat' => EtatDuSejour::Cloture]);
    Avis::create(['sejour_id' => $sejour->id, 'note' => 5]);
    connecterUnAdministrateurPourAvis();

    $reponse = test()->getJson('/api/v1/backoffice/avis/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /avis/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterUnAdministrateurPourAvis();

    test()->getJson('/api/v1/backoffice/avis/export?format=xlsx')->assertOk();
});

it('ferme la modération des avis aux gestionnaires', function (): void {
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login(User::factory()->profil(Profil::Gestionnaire)->create()));

    test()->getJson('/api/v1/backoffice/avis')->assertForbidden();
});

// ---------------------------------------------------------------- exposition publique

it('n’expose au public que les avis PUBLIÉS d’un logement', function (): void {
    $client = User::factory()->create(['prenoms' => 'Aya', 'nom' => 'Koné']);

    $sejourPublie = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Cloture]);
    $sejourPublie->logement()->update(['etat_publication' => 'publie']);
    Avis::create(['sejour_id' => $sejourPublie->id, 'note' => 5, 'commentaire' => 'Parfait.', 'statut' => 'publie']);

    $sejourEnAttente = Sejour::factory()->create(['logement_id' => $sejourPublie->logement_id, 'client_id' => $client->id, 'etat' => EtatDuSejour::Cloture]);
    Avis::create(['sejour_id' => $sejourEnAttente->id, 'note' => 1, 'statut' => 'en_attente']);

    $reponse = test()->getJson("/api/v1/catalogue/logements/{$sejourPublie->logement->reference}")->assertOk();

    expect($reponse->json('data.avis'))->toHaveCount(1)
        ->and($reponse->json('data.avis.0.note'))->toBe(5)
        ->and($reponse->json('data.avis.0.client'))->toBe('Aya K.')
        ->and($reponse->json('data.note_moyenne'))->toBe(5);
});
