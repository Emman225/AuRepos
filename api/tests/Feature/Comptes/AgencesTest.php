<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterPourAgences(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('liste les agences', function (): void {
    connecterPourAgences(Profil::Administrateur);
    Agence::factory()->create(['nom' => 'Agence Cocody']);
    Agence::factory()->create(['nom' => 'Agence Marcory', 'active' => false]);

    $reponse = test()->getJson('/api/v1/backoffice/agences')->assertOk();

    expect($reponse->json('data.elements'))->toHaveCount(2);
});

it('filtre les agences par recherche et par statut actif', function (): void {
    connecterPourAgences(Profil::Administrateur);
    Agence::factory()->create(['nom' => 'Agence Cocody', 'active' => true]);
    Agence::factory()->create(['nom' => 'Agence Marcory', 'active' => false]);

    test()->getJson('/api/v1/backoffice/agences?recherche=Cocody')->assertOk()
        ->assertJsonCount(1, 'data.elements')
        ->assertJsonPath('data.elements.0.nom', 'Agence Cocody');

    test()->getJson('/api/v1/backoffice/agences?active=1')->assertOk()
        ->assertJsonCount(1, 'data.elements');
});

it('crée une agence', function (): void {
    connecterPourAgences(Profil::Administrateur);

    $reponse = test()->postJson('/api/v1/backoffice/agences', [
        'nom' => 'Agence Yopougon', 'adresse' => 'Boulevard principal', 'telephone' => '+2250700000000',
    ])->assertCreated();

    expect($reponse->json('data.nom'))->toBe('Agence Yopougon')
        ->and($reponse->json('data.adresse'))->toBe('Boulevard principal')
        ->and($reponse->json('data.active'))->toBeTrue();
});

it('refuse deux agences avec le même nom', function (): void {
    connecterPourAgences(Profil::Administrateur);
    Agence::factory()->create(['nom' => 'Agence Cocody']);

    test()->postJson('/api/v1/backoffice/agences', ['nom' => 'Agence Cocody'])->assertUnprocessable();
});

it('modifie une agence, y compris sa désactivation', function (): void {
    connecterPourAgences(Profil::Administrateur);
    $agence = Agence::factory()->create(['nom' => 'Agence Cocody', 'active' => true]);

    $reponse = test()->putJson("/api/v1/backoffice/agences/{$agence->id}", ['nom' => 'Agence Cocody', 'active' => false])
        ->assertOk();

    expect($reponse->json('data.active'))->toBeFalse();
    expect(Agence::find($agence->id)->active)->toBeFalse();
});

it('exporte la liste des agences en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecterPourAgences(Profil::Administrateur);
    Agence::factory()->create(['nom' => 'Agence Cocody']);

    $reponse = test()->getJson('/api/v1/backoffice/agences/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /agences/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterPourAgences(Profil::Administrateur);

    test()->getJson('/api/v1/backoffice/agences/export?format=xlsx')->assertOk();
});

it('un gestionnaire n’accède pas à la gestion des agences', function (): void {
    connecterPourAgences(Profil::Gestionnaire);

    test()->getJson('/api/v1/backoffice/agences')->assertForbidden();
    test()->postJson('/api/v1/backoffice/agences', ['nom' => 'Agence Test'])->assertForbidden();
});

it('un profil hors exploitation n’accède pas aux agences', function (): void {
    connecterPourAgences(Profil::Proprietaire);

    test()->getJson('/api/v1/backoffice/agences')->assertForbidden();
});
