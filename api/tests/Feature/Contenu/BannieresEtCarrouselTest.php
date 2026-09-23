<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Models\Banniere;
use App\Domain\Contenu\Models\Diapositive;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterPourLeContenu(Profil $profil): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// ---------------------------------------------------------------- bannières

it('gère les bannières : création, modification, suppression', function (): void {
    connecterPourLeContenu(Profil::Administrateur);

    $cree = test()->postJson('/api/v1/backoffice/bannieres', [
        'titre' => 'Promo rentrée', 'image_url' => 'https://exemple.ci/b.jpg', 'lien' => 'https://exemple.ci',
    ])->assertCreated();
    $id = $cree->json('data.id');
    expect($cree->json('data.actif'))->toBeTrue();

    test()->putJson("/api/v1/backoffice/bannieres/{$id}", ['actif' => false])->assertOk()
        ->assertJsonPath('data.actif', false);

    test()->deleteJson("/api/v1/backoffice/bannieres/{$id}")->assertOk();
    expect(Banniere::find($id))->toBeNull();
});

it('liste les bannières, actives et inactives, pour la gestion (contrairement à la page d’accueil)', function (): void {
    connecterPourLeContenu(Profil::Administrateur);
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Banniere::create(['titre' => 'Active', 'image_url' => 'https://exemple.ci/a.jpg', 'actif' => true, 'cree_par' => $auteur->id]);
    Banniere::create(['titre' => 'Inactive', 'image_url' => 'https://exemple.ci/b.jpg', 'actif' => false, 'cree_par' => $auteur->id]);

    test()->getJson('/api/v1/backoffice/bannieres')->assertOk()->assertJsonCount(2, 'data');
});

// ---------------------------------------------------------------- carrousel

it('gère les diapositives du carrousel : création, modification, suppression', function (): void {
    connecterPourLeContenu(Profil::Administrateur);

    $cree = test()->postJson('/api/v1/backoffice/carrousel', ['image_url' => 'https://exemple.ci/c.jpg', 'ordre' => 3])
        ->assertCreated();
    $id = $cree->json('data.id');

    test()->putJson("/api/v1/backoffice/carrousel/{$id}", ['legende' => 'Nos résidences', 'ordre' => 1])->assertOk()
        ->assertJsonPath('data.legende', 'Nos résidences');

    test()->deleteJson("/api/v1/backoffice/carrousel/{$id}")->assertOk();
    expect(Diapositive::find($id))->toBeNull();
});

it('exporte la liste des bannières en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecterPourLeContenu(Profil::Administrateur);
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Banniere::create(['titre' => 'Promo', 'image_url' => 'https://exemple.ci/a.jpg', 'actif' => true, 'cree_par' => $auteur->id]);

    $reponse = test()->getJson('/api/v1/backoffice/bannieres/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /bannieres/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterPourLeContenu(Profil::Administrateur);

    test()->getJson('/api/v1/backoffice/bannieres/export?format=xlsx')->assertOk();
});

it('exporte la liste du carrousel en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecterPourLeContenu(Profil::Administrateur);
    $auteur = User::factory()->profil(Profil::Administrateur)->create();
    Diapositive::create(['image_url' => 'https://exemple.ci/c.jpg', 'legende' => 'Nos résidences', 'actif' => true, 'cree_par' => $auteur->id]);

    $reponse = test()->getJson('/api/v1/backoffice/carrousel/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /carrousel/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterPourLeContenu(Profil::Administrateur);

    test()->getJson('/api/v1/backoffice/carrousel/export?format=xlsx')->assertOk();
});

it('réserve bannières et carrousel aux administrateurs', function (): void {
    connecterPourLeContenu(Profil::Gestionnaire);

    test()->getJson('/api/v1/backoffice/bannieres')->assertForbidden();
    test()->postJson('/api/v1/backoffice/bannieres', ['titre' => 'X', 'image_url' => 'https://exemple.ci/x.jpg'])->assertForbidden();
    test()->getJson('/api/v1/backoffice/carrousel')->assertForbidden();
    test()->postJson('/api/v1/backoffice/carrousel', ['image_url' => 'https://exemple.ci/x.jpg'])->assertForbidden();
});
