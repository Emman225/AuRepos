<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Extras\Models\Extra;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecteExtras(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

// ---------------------------------------------------------------- back office : CRUD

it('crée un extra au catalogue, réservé à un administrateur', function (): void {
    connecteExtras(User::factory()->profil(Profil::Administrateur)->create());

    $reponse = test()->postJson('/api/v1/backoffice/extras', [
        'nom' => 'Panier petit-déjeuner', 'description' => 'Servi en chambre à 7h.', 'prix' => 7500,
    ])->assertCreated();

    expect($reponse->json('data'))->toMatchArray(['nom' => 'Panier petit-déjeuner', 'prix' => 7500, 'actif' => true]);
    expect(Extra::count())->toBe(1);
});

it('modifie un extra, y compris pour le désactiver — jamais supprimé (des commandes peuvent déjà le référencer)', function (): void {
    connecteExtras(User::factory()->profil(Profil::Administrateur)->create());
    $extra = Extra::factory()->create(['actif' => true]);

    test()->putJson("/api/v1/backoffice/extras/{$extra->id}", [
        'nom' => $extra->nom, 'prix' => 12000, 'actif' => false,
    ])->assertOk()->assertJsonPath('data.prix', 12000)->assertJsonPath('data.actif', false);
});

it('ferme le catalogue au-delà des administrateurs', function (): void {
    connecteExtras(User::factory()->profil(Profil::Gestionnaire)->create());

    test()->getJson('/api/v1/backoffice/extras')->assertForbidden();
    test()->postJson('/api/v1/backoffice/extras', ['nom' => 'X', 'prix' => 1000])->assertForbidden();
});

it('exige un nom et un prix valides', function (): void {
    connecteExtras(User::factory()->profil(Profil::Administrateur)->create());

    test()->postJson('/api/v1/backoffice/extras', ['nom' => '', 'prix' => -1])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['nom', 'prix']]);
});

// ---------------------------------------------------------------- espace client : catalogue

it('le client ne parcourt que les extras actifs', function (): void {
    Extra::factory()->create(['nom' => 'Late check-out', 'actif' => true]);
    Extra::factory()->create(['nom' => 'Ancien extra retiré', 'actif' => false]);

    connecteExtras(User::factory()->create());
    $reponse = test()->getJson('/api/v1/client/extras')->assertOk();

    expect($reponse->json('data'))->toHaveCount(1)->and($reponse->json('data.0.nom'))->toBe('Late check-out');
});
