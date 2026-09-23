<?php

use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('refuse l’accès au détail du compte sans authentification', function (): void {
    test()->getJson('/api/v1/client/compte')->assertUnauthorized();
});

it('renvoie mes coordonnées et mon régime b2c par défaut', function (): void {
    $utilisateur = User::factory()->create(['nom' => 'Koné', 'prenoms' => 'Awa', 'telephone' => '0707070707']);
    test()->withToken(auth('api')->login($utilisateur));

    test()->getJson('/api/v1/client/compte')
        ->assertOk()
        ->assertJsonPath('data.nom', 'Koné')
        ->assertJsonPath('data.prenoms', 'Awa')
        ->assertJsonPath('data.nom_complet', 'Awa Koné')
        ->assertJsonPath('data.email', $utilisateur->email)
        ->assertJsonPath('data.telephone', '0707070707')
        ->assertJsonPath('data.nature', 'b2c')
        ->assertJsonPath('data.nature_libelle', 'Particulier')
        ->assertJsonPath('data.tva_hebergement', true);
});

it('reflète la fiche client (organisation) quand elle existe déjà', function (): void {
    $utilisateur = User::factory()->create();
    Client::de($utilisateur)->update(['nature' => 'b2b', 'raison_sociale' => 'ACME SARL', 'ncc' => '1234567A']);
    test()->withToken(auth('api')->login($utilisateur));

    test()->getJson('/api/v1/client/compte')
        ->assertOk()
        ->assertJsonPath('data.nature', 'b2b')
        ->assertJsonPath('data.nature_libelle', 'Entreprise')
        ->assertJsonPath('data.raison_sociale', 'ACME SARL')
        ->assertJsonPath('data.ncc', '1234567A');
});
