<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterPourLeBlog(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('crée un article en brouillon, avec un identifiant d’adresse (slug) généré et unique', function (): void {
    connecterPourLeBlog(Profil::Administrateur);

    $reponse = test()->postJson('/api/v1/backoffice/articles', [
        'titre' => 'Bienvenue à Abidjan', 'contenu' => 'Un texte de présentation.',
    ])->assertCreated();

    expect($reponse->json('data.slug'))->toBe('bienvenue-a-abidjan')
        ->and($reponse->json('data.statut'))->toBe('brouillon')
        ->and($reponse->json('data.publie_le'))->toBeNull();

    // Un second article au même titre reçoit un identifiant distinct, jamais une collision silencieuse.
    $second = test()->postJson('/api/v1/backoffice/articles', [
        'titre' => 'Bienvenue à Abidjan', 'contenu' => 'Autre texte.',
    ])->assertCreated();
    expect($second->json('data.slug'))->toBe('bienvenue-a-abidjan-2');
});

it('date la première publication et la conserve même après une republication', function (): void {
    connecterPourLeBlog(Profil::Administrateur);
    $article = Article::create(['titre' => 'T', 'slug' => 't', 'contenu' => 'C', 'statut' => 'brouillon', 'auteur_id' => User::factory()->profil(Profil::Administrateur)->create()->id]);

    $reponse = test()->putJson("/api/v1/backoffice/articles/{$article->id}", ['statut' => 'publie'])->assertOk();
    $premierePublication = $reponse->json('data.publie_le');
    expect($premierePublication)->not->toBeNull();

    test()->putJson("/api/v1/backoffice/articles/{$article->id}", ['statut' => 'brouillon'])->assertOk()
        ->assertJsonPath('data.statut', 'brouillon');

    test()->travel(1)->hours();
    $republication = test()->putJson("/api/v1/backoffice/articles/{$article->id}", ['statut' => 'publie'])->assertOk();
    expect($republication->json('data.publie_le'))->not->toBe($premierePublication);
});

it('n’expose au public que les articles publiés, jamais les brouillons', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Article::create(['titre' => 'Publié', 'slug' => 'publie', 'contenu' => 'C', 'statut' => 'publie', 'publie_le' => now(), 'auteur_id' => $auteur->id]);
    Article::create(['titre' => 'Brouillon', 'slug' => 'brouillon-x', 'contenu' => 'C', 'statut' => 'brouillon', 'auteur_id' => $auteur->id]);

    $reponse = test()->getJson('/api/v1/blog')->assertOk();
    expect($reponse->json('data.elements'))->toHaveCount(1)
        ->and($reponse->json('data.elements.0.slug'))->toBe('publie')
        ->and($reponse->json('data.elements.0'))->not->toHaveKey('contenu');

    test()->getJson('/api/v1/blog/publie')->assertOk()->assertJsonPath('data.titre', 'Publié');
    test()->getJson('/api/v1/blog/brouillon-x')->assertNotFound();
});

it('exporte la liste des articles en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecterPourLeBlog(Profil::Administrateur);
    Article::create(['titre' => 'Un article', 'slug' => 'un-article', 'contenu' => 'C', 'statut' => 'brouillon', 'auteur_id' => User::factory()->profil(Profil::Administrateur)->create()->id]);

    $reponse = test()->getJson('/api/v1/backoffice/articles/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /articles/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterPourLeBlog(Profil::Administrateur);

    test()->getJson('/api/v1/backoffice/articles/export?format=xlsx')->assertOk();
});

it('réserve la gestion du blog aux administrateurs', function (): void {
    connecterPourLeBlog(Profil::Gestionnaire);

    test()->getJson('/api/v1/backoffice/articles')->assertForbidden();
    test()->postJson('/api/v1/backoffice/articles', ['titre' => 'X', 'contenu' => 'Y'])->assertForbidden();
});
