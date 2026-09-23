<?php

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Services\Caisse;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Enums\StatutCompte;
use App\Domain\Comptes\Models\Agence;
use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Sejours\Models\Sejour;
use App\Mail\BienvenuePartenaireMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('local');
});

function seConnecterEnBackoffice(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// ---------------------------------------------------------------- création

it('crée ensemble le compte de connexion et la fiche apporteur, et invite à choisir un mot de passe', function (): void {
    seConnecterEnBackoffice(Profil::Gestionnaire);

    $reponse = test()->postJson('/api/v1/backoffice/apporteurs', [
        'nom' => 'Kouassi', 'prenoms' => 'Awa', 'email' => 'AWA@exemple.ci', 'pourcentage' => 12,
    ])->assertCreated();

    $compte = User::firstWhere('email', 'awa@exemple.ci');
    expect($compte->profil)->toBe(Profil::Apporteur)
        ->and($compte->statut)->toBe(StatutCompte::Actif)
        ->and((float) $reponse->json('data.pourcentage'))->toBe(12.0)
        ->and($reponse->json('data.actif'))->toBeTrue()
        ->and($reponse->json('data.code'))->not->toBeEmpty();

    Mail::assertQueued(BienvenuePartenaireMail::class, fn ($m) => $m->hasTo('awa@exemple.ci'));
    expect($reponse->getContent())->not->toContain('password');
});

it('refuse un pourcentage hors bornes ou manquant', function (): void {
    seConnecterEnBackoffice(Profil::Administrateur);

    test()->postJson('/api/v1/backoffice/apporteurs', ['nom' => 'X', 'email' => 'x@exemple.ci'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['pourcentage']]);

    test()->postJson('/api/v1/backoffice/apporteurs', ['nom' => 'X', 'email' => 'x@exemple.ci', 'pourcentage' => 150])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['pourcentage']]);
});

// ---------------------------------------------------------------- modification

it('modifie le pourcentage et désactive un apporteur', function (): void {
    seConnecterEnBackoffice(Profil::Administrateur);
    $apporteur = Apporteur::factory()->create(['pourcentage' => 10, 'actif' => true]);

    $reponse = test()->putJson("/api/v1/backoffice/apporteurs/{$apporteur->id}", ['pourcentage' => 20, 'actif' => false])
        ->assertOk()
        ->assertJsonPath('data.actif', false);
    expect((float) $reponse->json('data.pourcentage'))->toBe(20.0);

    expect($apporteur->refresh()->actif)->toBeFalse();
});

// ---------------------------------------------------------------- liste

it('liste les apporteurs et cherche par nom, courriel ou code', function (): void {
    seConnecterEnBackoffice(Profil::Administrateur);
    Apporteur::factory()->create(['user_id' => User::factory()->profil(Profil::Apporteur)->create(['nom' => 'Bamba'])->id]);
    Apporteur::factory()->create();

    $tous = test()->getJson('/api/v1/backoffice/apporteurs')->assertOk()->json('data.elements');
    expect($tous)->toHaveCount(2);

    $recherche = test()->getJson('/api/v1/backoffice/apporteurs?recherche=bamb')->assertOk()->json('data.elements');
    expect($recherche)->toHaveCount(1)->and($recherche[0]['compte']['nom'])->toBe('Bamba');
});

// ---------------------------------------------------------------- commissions et solde dû

it('affiche les commissions d’un apporteur et ce qui lui reste dû', function (): void {
    $admin = seConnecterEnBackoffice(Profil::Administrateur);
    $apporteur = Apporteur::factory()->create(['pourcentage' => 10]);
    $client = User::factory()->create(['parraine_par_id' => $apporteur->id]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'net_a_payer' => 50000]);

    $agence = Agence::factory()->create();
    $caissier = User::factory()->profil(Profil::Gestionnaire)->create(['agence_id' => $agence->id]);
    $a2 = User::factory()->profil(Profil::Administrateur)->create(['agence_id' => $agence->id]);
    $caisse = app(Caisse::class);
    $r = $caisse->saisirUnEncaissement($caissier, $client, [$sejour->id], 50000, ModeDeReglement::Especes, 'Solde');
    $caisse->valider($r, $admin);
    $caisse->joindreLaPreuve($r->refresh(), $a2, UploadedFile::fake()->create('p.pdf', 10, 'application/pdf'));
    $caisse->finaliser($r->refresh(), $a2);

    $reponse = test()->getJson("/api/v1/backoffice/apporteurs/{$apporteur->id}/commissions")->assertOk();

    expect($reponse->json('data.solde_du'))->toBe(5000)
        ->and($reponse->json('data.commissions'))->toHaveCount(1)
        ->and($reponse->json('data.commissions.0.montant'))->toBe(5000)
        ->and($reponse->json('data.commissions.0.sejour_reference'))->toBe($sejour->reference);
});

// ---------------------------------------------------------------- accès

it('ferme les apporteurs aux profils hors exploitation', function (Profil $profil): void {
    $apporteur = Apporteur::factory()->create();
    seConnecterEnBackoffice($profil);

    test()->getJson('/api/v1/backoffice/apporteurs')->assertForbidden();
    test()->getJson("/api/v1/backoffice/apporteurs/{$apporteur->id}/commissions")->assertForbidden();
})->with([Profil::Apporteur, Profil::Client, Profil::Gouvernante]);
