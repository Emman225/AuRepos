<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Restaurateur;
use App\Mail\BienvenuePartenaireMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

function connecterPourRepas(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// ---------------------------------------------------------------- création

it('crée ensemble le compte de connexion et la fiche restaurateur, et invite à choisir un mot de passe', function (): void {
    connecterPourRepas(Profil::Gestionnaire);

    $reponse = test()->postJson('/api/v1/backoffice/restaurateurs', [
        'nom' => 'Yao', 'prenoms' => 'Serge', 'email' => 'SERGE@exemple.ci', 'assujetti_tva' => true,
    ])->assertCreated();

    $compte = User::firstWhere('email', 'serge@exemple.ci');
    expect($compte->profil)->toBe(Profil::Restaurateur)
        ->and($compte->statut)->toBe(StatutCompte::Actif)
        ->and($reponse->json('data.assujetti_tva'))->toBeTrue()
        ->and($reponse->json('data.actif'))->toBeTrue()
        ->and($reponse->json('data.pourcentage_plateforme'))->toBeNull();

    Mail::assertQueued(BienvenuePartenaireMail::class, fn ($m) => $m->hasTo('serge@exemple.ci'));
});

// ---------------------------------------------------------------- modification

it('modifie l’assujettissement à la TVA et désactive un restaurateur, sans toucher au pourcentage', function (): void {
    connecterPourRepas(Profil::Administrateur);
    $restaurateur = Restaurateur::factory()->avecPourcentage(25)->create(['assujetti_tva' => false, 'actif' => true]);

    $reponse = test()->putJson("/api/v1/backoffice/restaurateurs/{$restaurateur->id}", ['assujetti_tva' => true, 'actif' => false])
        ->assertOk()
        ->assertJsonPath('data.actif', false)
        ->assertJsonPath('data.assujetti_tva', true);

    expect((float) $reponse->json('data.pourcentage_plateforme'))->toBe(25.0)
        ->and($restaurateur->refresh()->actif)->toBeFalse();
});

it('n’accepte pas le pourcentage plateforme dans le formulaire ordinaire', function (): void {
    connecterPourRepas(Profil::Administrateur);
    $restaurateur = Restaurateur::factory()->create();

    test()->putJson("/api/v1/backoffice/restaurateurs/{$restaurateur->id}", ['pourcentage_plateforme' => 40])->assertOk();

    // Le champ est ignoré : il n'est même pas dans les règles de validation de la requête.
    expect($restaurateur->refresh()->pourcentage_plateforme)->toBeNull();
});

// ---------------------------------------------------------------- liste

it('liste les restaurateurs et filtre par actif', function (): void {
    connecterPourRepas(Profil::Administrateur);
    Restaurateur::factory()->create(['actif' => true]);
    Restaurateur::factory()->inactif()->create();

    $tous = test()->getJson('/api/v1/backoffice/restaurateurs')->assertOk()->json('data.elements');
    expect($tous)->toHaveCount(2);

    $actifs = test()->getJson('/api/v1/backoffice/restaurateurs?actif=1')->assertOk()->json('data.elements');
    expect($actifs)->toHaveCount(1);
});

// ---------------------------------------------------------------- double validation du pourcentage plateforme

it('propose le pourcentage plateforme : il n’entre en vigueur qu’après validation par un second administrateur', function (): void {
    $a1 = connecterPourRepas(Profil::Administrateur);
    $a2 = User::factory()->profil(Profil::Administrateur)->create();
    $restaurateur = Restaurateur::factory()->create();

    $reponse = test()->postJson("/api/v1/backoffice/restaurateurs/{$restaurateur->id}/pourcentage/proposer", ['pourcentage' => 30, 'motif' => 'Ouverture'])
        ->assertCreated();

    // Toujours aucun pourcentage tant que personne d'autre n'a validé.
    expect($restaurateur->refresh()->pourcentage_plateforme)->toBeNull();

    $id = $reponse->json('data.id');
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($a2));
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])->assertOk();

    expect((float) $restaurateur->refresh()->pourcentage_plateforme)->toBe(30.0);
});

it('refuse qu’un pourcentage hors limites soit proposé', function (): void {
    connecterPourRepas(Profil::Administrateur);
    $restaurateur = Restaurateur::factory()->create();

    test()->postJson("/api/v1/backoffice/restaurateurs/{$restaurateur->id}/pourcentage/proposer", ['pourcentage' => 600])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['pourcentage']]);
});

it('refuse qu’un même administrateur propose ET valide le pourcentage plateforme', function (): void {
    connecterPourRepas(Profil::Administrateur);
    $restaurateur = Restaurateur::factory()->create();

    $id = test()->postJson("/api/v1/backoffice/restaurateurs/{$restaurateur->id}/pourcentage/proposer", ['pourcentage' => 30])->json('data.id');

    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider'])
        ->assertStatus(403)->assertJsonPath('errors.code.0', 'validation_de_sa_propre_saisie');
});

it('ferme la proposition de pourcentage aux gestionnaires', function (): void {
    connecterPourRepas(Profil::Gestionnaire);
    $restaurateur = Restaurateur::factory()->create();

    test()->postJson("/api/v1/backoffice/restaurateurs/{$restaurateur->id}/pourcentage/proposer", ['pourcentage' => 30])
        ->assertForbidden();
});

// ---------------------------------------------------------------- accès

it('ferme les restaurateurs aux profils hors exploitation', function (Profil $profil): void {
    $restaurateur = Restaurateur::factory()->create();
    connecterPourRepas($profil);

    test()->getJson('/api/v1/backoffice/restaurateurs')->assertForbidden();
    test()->getJson("/api/v1/backoffice/restaurateurs/{$restaurateur->id}/dette")->assertForbidden();
})->with([Profil::Restaurateur, Profil::Client, Profil::Livreur]);
