<?php

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('liste les pages statiques et les logements publiés, jamais les brouillons', function (): void {
    $publie = Logement::factory()->create(['etat_publication' => EtatPublication::Publie]);
    $brouillon = Logement::factory()->create(['etat_publication' => EtatPublication::Brouillon]);

    $reponse = test()->get('/api/v1/sitemap.xml')->assertOk();

    $xml = $reponse->getContent();
    expect($xml)->toContain('<loc>'.config('plateforme.url_du_site').'/</loc>')
        ->toContain('<loc>'.config('plateforme.url_du_site').'/recherche</loc>')
        ->toContain('<loc>'.config('plateforme.url_du_site').'/blog</loc>')
        ->toContain('<loc>'.config('plateforme.url_du_site').'/conditions-generales</loc>')
        ->toContain('/logements/'.$publie->reference.'</loc>')
        ->not->toContain('/logements/'.$brouillon->reference.'</loc>');

    expect($reponse->headers->get('Content-Type'))->toContain('application/xml');
});

it('liste les articles publiés, jamais les brouillons', function (): void {
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Article::create(['titre' => 'Publié', 'slug' => 'article-publie', 'contenu' => 'C', 'statut' => 'publie', 'publie_le' => now(), 'auteur_id' => $auteur->id]);
    Article::create(['titre' => 'Brouillon', 'slug' => 'article-brouillon', 'contenu' => 'C', 'statut' => 'brouillon', 'auteur_id' => $auteur->id]);

    $xml = test()->get('/api/v1/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('/blog/article-publie</loc>')
        ->not->toContain('/blog/article-brouillon</loc>');
});

it('exclut un logement d’une résidence désactivée, comme la page d’accueil', function (): void {
    $logement = Logement::factory()->create(['etat_publication' => EtatPublication::Publie]);
    $logement->residence()->update(['active' => false]);

    $xml = test()->get('/api/v1/sitemap.xml')->assertOk()->getContent();

    expect($xml)->not->toContain('/logements/'.$logement->reference.'</loc>');
});
