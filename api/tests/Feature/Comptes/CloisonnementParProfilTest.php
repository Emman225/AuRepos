<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Support\Api\ReponseApi;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
| Règle du cahier des charges (§ 3) : un utilisateur ne peut pas atteindre
| un écran qui ne relève pas de son profil — même en tapant l'adresse.
*/

beforeEach(function (): void {
    Route::middleware(['api', 'connecte', 'profil:super_administrateur,administrateur'])
        ->get('api/v1/_essai/reserve-aux-administrateurs', fn () => ReponseApi::succes('ok'));
});

function appelerEnTantQue(User $utilisateur)
{
    return test()->withToken(auth('api')->login($utilisateur))->getJson('/api/v1/_essai/reserve-aux-administrateurs');
}

it('laisse passer les profils autorisés', function (Profil $profil): void {
    appelerEnTantQue(User::factory()->profil($profil)->create())->assertOk();
})->with([Profil::SuperAdministrateur, Profil::Administrateur]);

it('refuse tous les autres profils avec un 403', function (Profil $profil): void {
    appelerEnTantQue(User::factory()->profil($profil)->create())
        ->assertForbidden()
        ->assertJsonPath('success', false);
})->with([
    Profil::Gestionnaire, Profil::Gouvernante, Profil::AgentAssistance, Profil::Proprietaire,
    Profil::AgentTerrain, Profil::Chauffeur, Profil::Livreur, Profil::Restaurateur,
    Profil::Apporteur, Profil::Client,
]);

it('refuse un visiteur non connecté avec un 401', function (): void {
    test()->getJson('/api/v1/_essai/reserve-aux-administrateurs')->assertUnauthorized();
});

it('coupe l’accès d’un compte bloqué sans attendre la fin de son jeton', function (): void {
    $admin = User::factory()->profil(Profil::Administrateur)->create();
    $jeton = auth('api')->login($admin);

    $admin->update(['statut' => StatutCompte::Bloque]);
    auth('api')->forgetUser();

    test()->withToken($jeton)->getJson('/api/v1/_essai/reserve-aux-administrateurs')->assertUnauthorized();
});

it('retire les accès dès que le profil change, sans attendre la fin du jeton', function (): void {
    $admin = User::factory()->profil(Profil::Administrateur)->create();
    $jeton = auth('api')->login($admin);

    $admin->update(['profil' => Profil::Gestionnaire]);
    auth('api')->forgetUser();

    test()->withToken($jeton)->getJson('/api/v1/_essai/reserve-aux-administrateurs')->assertForbidden();
});

it('refuse en base un profil qui n’existe pas', function (): void {
    expect(fn () => DB::table('users')->insert([
        'nom' => 'X', 'email' => 'x@exemple.ci', 'password' => 'x', 'profil' => 'pirate', 'statut' => 'actif',
    ]))->toThrow(QueryException::class);
});

it('couvre les douze profils du cahier des charges, chacun avec un espace', function (): void {
    expect(Profil::cases())->toHaveCount(12);

    foreach (Profil::cases() as $profil) {
        expect($profil->espace())->not->toBeEmpty()
            ->and($profil->libelle())->not->toBeEmpty();
    }
});
