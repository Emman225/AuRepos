<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Livreur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

function connecterPourLivreurs(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('crée un livreur depuis le back office', function (): void {
    connecterPourLivreurs(Profil::Gestionnaire);

    $reponse = test()->postJson('/api/v1/backoffice/livreurs', [
        'nom' => 'Kone', 'prenoms' => 'Ali', 'email' => 'ali@exemple.ci',
    ])->assertCreated();

    expect(User::firstWhere('email', 'ali@exemple.ci')->profil)->toBe(Profil::Livreur)
        ->and($reponse->json('data.actif'))->toBeTrue();
});

it('modifie et désactive un livreur', function (): void {
    connecterPourLivreurs(Profil::Administrateur);
    $livreur = Livreur::factory()->create();

    test()->putJson("/api/v1/backoffice/livreurs/{$livreur->id}", ['actif' => false])
        ->assertOk()->assertJsonPath('data.actif', false);

    expect($livreur->refresh()->actif)->toBeFalse();
});

it('liste les livreurs', function (): void {
    connecterPourLivreurs(Profil::Administrateur);
    Livreur::factory()->count(2)->create();

    $reponse = test()->getJson('/api/v1/backoffice/livreurs')->assertOk();
    expect($reponse->json('data.elements'))->toHaveCount(2);
});

it('ferme les livreurs aux profils hors exploitation', function (): void {
    connecterPourLivreurs(Profil::Client);

    test()->getJson('/api/v1/backoffice/livreurs')->assertForbidden();
});
