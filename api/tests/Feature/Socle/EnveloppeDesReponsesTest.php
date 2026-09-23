<?php

use App\Support\Api\ReponseApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Contrat de base de l'API : le code HTTP dit la vérité, et le corps a
| toujours la même forme { success, message, data, errors }.
*/

beforeEach(function (): void {
    // Routes jetables, déclarées pour l'essai seulement.
    Route::middleware('api')->prefix('api/v1/_essai')->group(function (): void {
        Route::post('validation', fn (Request $r) => ReponseApi::succes($r->validate(['nom' => 'required|min:3'])));
        Route::get('non-connecte', fn () => throw new AuthenticationException);
        Route::get('mauvais-profil', fn () => throw new AuthorizationException);
        Route::get('panne', fn () => throw new RuntimeException('SQLSTATE[42P01]: table "sejours" inconnue'));
        Route::get('creation', fn () => ReponseApi::cree(['id' => 7]));
    });
});

it('répond 200 avec l’enveloppe complète sur le point de contrôle', function (): void {
    $this->getJson('/api/v1/etat')
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data' => ['application', 'version_api', 'base_de_donnees', 'heure_serveur'], 'errors'])
        ->assertJsonPath('success', true)
        ->assertJsonPath('errors', null);
});

it('répond 201 à une création', function (): void {
    $this->getJson('/api/v1/_essai/creation')
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', 7);
});

it('répond 404 dans l’enveloppe pour une adresse inconnue', function (): void {
    $this->getJson('/api/v1/adresse-qui-n-existe-pas')
        ->assertNotFound()
        ->assertExactJson(['success' => false, 'message' => 'Élément introuvable.', 'data' => null, 'errors' => null]);
});

it('répond 422 avec le détail par champ quand la saisie est incorrecte', function (): void {
    $this->postJson('/api/v1/_essai/validation', ['nom' => 'ab'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['errors' => ['nom']]);
});

it('répond 401 à un visiteur non connecté', function (): void {
    $this->getJson('/api/v1/_essai/non-connecte')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Vous devez vous connecter.');
});

it('répond 403 quand l’écran ne relève pas du profil', function (): void {
    $this->getJson('/api/v1/_essai/mauvais-profil')
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

it('ne laisse jamais sortir le message d’une panne technique', function (): void {
    $reponse = $this->getJson('/api/v1/_essai/panne')->assertStatus(500);

    expect($reponse->getContent())
        ->not->toContain('SQLSTATE')
        ->not->toContain('sejours');
    $reponse->assertJsonPath('message', 'Une erreur technique est survenue. Elle a été enregistrée.');
});
