<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use App\Domain\Sejours\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterPourComptesATerme(): User
{
    $administrateur = User::factory()->profil(Profil::Administrateur)->create();
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($administrateur));

    return $administrateur;
}

it('pagine la file des demandes de compte à terme selon par_page', function (): void {
    connecterPourComptesATerme();
    $utilisateur = User::factory()->create();
    Client::de($utilisateur)->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);

    test()->getJson('/api/v1/backoffice/clients-a-terme?par_page=5')->assertOk()
        ->assertJsonPath('data.pagination.par_page', 5);
});

it('exporte la liste des demandes de compte à terme en Excel, Word et PDF avec les mêmes filtres que l’écran', function (string $format, string $typeMime) {
    connecterPourComptesATerme();
    $utilisateur = User::factory()->create();
    Client::de($utilisateur)->update(['nature' => 'b2b', 'statut_a_terme' => StatutDemandeATerme::EnAttente, 'demande_a_terme_le' => now()]);

    $reponse = test()->getJson('/api/v1/backoffice/clients-a-terme/export?format='.$format);

    $reponse->assertOk();
    expect($reponse->headers->get('Content-Type'))->toBe($typeMime);
    expect($reponse->headers->get('Content-Disposition'))->toContain('attachment');
})->with([
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    ['pdf', 'application/pdf'],
]);

it('route /clients-a-terme/export avant toute route qui prendrait « export » pour un identifiant', function (): void {
    connecterPourComptesATerme();

    test()->getJson('/api/v1/backoffice/clients-a-terme/export?format=xlsx')->assertOk();
});
