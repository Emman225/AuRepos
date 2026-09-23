<?php

use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\BaremeLivraisonRepas;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterAdminRepas(): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil(Profil::Administrateur)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('crée et modifie un barème de livraison repas, un seul forfait par résidence', function (): void {
    connecterAdminRepas();
    $residence = Residence::factory()->create();

    $reponse = test()->postJson('/api/v1/backoffice/baremes-livraison-repas', ['residence_id' => $residence->id, 'forfait' => 1500])
        ->assertCreated();

    $id = $reponse->json('data.id');
    test()->putJson("/api/v1/backoffice/baremes-livraison-repas/{$id}", ['forfait' => 2000])
        ->assertOk()->assertJsonPath('data.forfait', 2000);

    // Un second forfait pour la même résidence est refusé par la contrainte d'unicité.
    test()->postJson('/api/v1/backoffice/baremes-livraison-repas', ['residence_id' => $residence->id, 'forfait' => 1000])
        ->assertStatus(500);
});

it('ferme les barèmes de livraison repas aux gestionnaires', function (): void {
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $gestionnaire = User::factory()->profil(Profil::Gestionnaire)->create();
    test()->withToken(auth('api')->login($gestionnaire));
    BaremeLivraisonRepas::factory()->create();

    test()->getJson('/api/v1/backoffice/baremes-livraison-repas')->assertForbidden();
});
