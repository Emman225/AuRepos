<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\AbonneNewsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterPourLaNewsletter(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('inscrit une adresse à la lettre d’information, en libre-service', function (): void {
    test()->postJson('/api/v1/newsletter/abonnement', ['email' => 'aya@exemple.ci', 'nom' => 'Aya K.'])
        ->assertCreated();

    $abonne = AbonneNewsletter::where('email', 'aya@exemple.ci')->sole();
    expect($abonne->actif)->toBeTrue()->and($abonne->nom)->toBe('Aya K.');
});

it('réinscrit une adresse déjà désinscrite, sans doublon', function (): void {
    AbonneNewsletter::create(['email' => 'aya@exemple.ci', 'actif' => false, 'abonne_le' => now()->subMonth(), 'desabonne_le' => now()->subDay()]);

    test()->postJson('/api/v1/newsletter/abonnement', ['email' => 'aya@exemple.ci'])->assertCreated();

    expect(AbonneNewsletter::where('email', 'aya@exemple.ci')->count())->toBe(1);
    $abonne = AbonneNewsletter::where('email', 'aya@exemple.ci')->sole();
    expect($abonne->actif)->toBeTrue()->and($abonne->desabonne_le)->toBeNull();
});

it('refuse une adresse mal formée', function (): void {
    test()->postJson('/api/v1/newsletter/abonnement', ['email' => 'pas-une-adresse'])->assertUnprocessable();
});

it('liste et désinscrit un abonné depuis le back office, réservé aux administrateurs', function (): void {
    $abonne = AbonneNewsletter::create(['email' => 'aya@exemple.ci', 'actif' => true, 'abonne_le' => now()]);

    connecterPourLaNewsletter(Profil::Gestionnaire);
    test()->getJson('/api/v1/backoffice/newsletter')->assertForbidden();

    connecterPourLaNewsletter(Profil::Administrateur);
    test()->getJson('/api/v1/backoffice/newsletter')->assertOk()->assertJsonCount(1, 'data.elements');

    test()->putJson("/api/v1/backoffice/newsletter/{$abonne->id}/desabonner")->assertOk()
        ->assertJsonPath('data.actif', false);

    expect($abonne->refresh()->actif)->toBeFalse()->and($abonne->desabonne_le)->not->toBeNull();
});

it('exporte la liste des abonnés en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    AbonneNewsletter::create(['email' => 'aya@exemple.ci', 'actif' => true, 'abonne_le' => now()]);
    connecterPourLaNewsletter(Profil::Administrateur);

    $reponse = test()->getJson('/api/v1/backoffice/newsletter/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /newsletter/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterPourLaNewsletter(Profil::Administrateur);

    test()->getJson('/api/v1/backoffice/newsletter/export?format=xlsx')->assertOk();
});
