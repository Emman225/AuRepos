<?php

use App\Domain\Assistance\Enums\EtatDeLaReclamation;
use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: Sejour, 2: Commande} */
function uneCommandeLivreeDuClient(): array
{
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id]);
    $commande = Commande::factory()->create(['sejour_id' => $sejour->id, 'etat' => EtatDeCommande::Livree]);

    return [$client, $sejour, $commande];
}

function connecterRepasReclamation(User $u): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($u));
}

// -------------------------------------------------------- création, motif ≥ 15 caractères, mêmes règles que la réclamation séjour

it('refuse une réclamation repas sur une commande pas encore livrée', function (): void {
    [$client, $sejour, $commande] = uneCommandeLivreeDuClient();
    $commande->update(['etat' => EtatDeCommande::EnPreparation]);
    connecterRepasReclamation($client);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commande->id}/reclamations", ['motif' => str_repeat('a', 20)])
        ->assertStatus(422)->assertJsonPath('errors.code.0', 'commande_non_livree');
});

it('refuse un motif de moins de 15 caractères sur une réclamation repas', function (): void {
    [$client, $sejour, $commande] = uneCommandeLivreeDuClient();
    connecterRepasReclamation($client);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commande->id}/reclamations", ['motif' => 'Trop court'])
        ->assertStatus(422)->assertJsonStructure(['errors' => ['motif']]);
});

it('accepte une réclamation repas sur une commande livrée : sejour_id nul, commande_id posé', function (): void {
    [$client, $sejour, $commande] = uneCommandeLivreeDuClient();
    connecterRepasReclamation($client);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commande->id}/reclamations", ['motif' => str_repeat('b', 15)])
        ->assertCreated()->assertJsonPath('data.statut', 'ouverte');

    expect(Reclamation::count())->toBe(1);
    $reclamation = Reclamation::first();
    expect($reclamation->sejour_id)->toBeNull()
        ->and($reclamation->commande_id)->toBe($commande->id)
        ->and($reclamation->client_id)->toBe($client->id)
        ->and($reclamation->libelleAudit())->toContain($commande->reference);
});

it('la commande d’un autre séjour n’existe pas pour moi : 404', function (): void {
    [, $sejour, $commande] = uneCommandeLivreeDuClient();
    $autreClient = User::factory()->profil(Profil::Client)->create();
    connecterRepasReclamation($autreClient);

    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commande->id}/reclamations", ['motif' => str_repeat('c', 20)])
        ->assertNotFound();
});

// -------------------------------------------------------- avoir / geste commercial : EXACTEMENT le même circuit que la réclamation séjour

it('LE trésorier désigné confirme un avoir sur une réclamation repas : la réclamation ferme et un décaissement est saisi', function (): void {
    [$client, $sejour, $commande] = uneCommandeLivreeDuClient();
    $admin1 = User::factory()->profil(Profil::Administrateur)->create();
    $admin2 = User::factory()->profil(Profil::Administrateur)->create();
    $tresorier = User::factory()->profil(Profil::Administrateur)->create();
    app(Parametres::class)->enregistrer('gestionnaires', ['validant_2_id' => $tresorier->id], $admin1);

    connecterRepasReclamation($client);
    $reclamation = test()->postJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commande->id}/reclamations", ['motif' => str_repeat('d', 20)])->json('data');

    connecterRepasReclamation($admin2);
    $id = test()->postJson("/api/v1/backoffice/reclamations/{$reclamation['id']}/avoir", ['montant' => 3000, 'motif' => 'Plat froid à la livraison'])
        ->assertCreated()->json('data.id');
    expect(Reclamation::find($reclamation['id'])->statut)->toBe(EtatDeLaReclamation::EnCours);

    connecterRepasReclamation($tresorier);
    test()->putJson("/api/v1/backoffice/changements/{$id}/decision", ['decision' => 'valider', 'mode_de_remboursement' => 'especes'])
        ->assertOk();

    $ligne = Reclamation::find($reclamation['id']);
    expect($ligne->statut)->toBe(EtatDeLaReclamation::Fermee)
        ->and($ligne->avoir_montant)->toBe(3000)
        ->and($ligne->reglement_id)->not->toBeNull();

    $reglement = Reglement::find($ligne->reglement_id);
    expect($reglement->sens)->toBe('decaissement')
        ->and($reglement->guichet)->toBe(Guichet::Remboursements)
        ->and($reglement->tiers_id)->toBe($client->id)
        ->and($reglement->montant)->toBe(3000);
});

it('le back office liste les réclamations repas et séjour ensemble', function (): void {
    [$client, $sejour, $commande] = uneCommandeLivreeDuClient();
    connecterRepasReclamation($client);
    test()->postJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commande->id}/reclamations", ['motif' => str_repeat('e', 20)])->assertCreated();

    $admin = User::factory()->profil(Profil::Administrateur)->create();
    connecterRepasReclamation($admin);
    $reponse = test()->getJson('/api/v1/backoffice/reclamations')->assertOk();

    expect($reponse->json('data.elements'))->toHaveCount(1)
        ->and($reponse->json('data.elements.0.sejour'))->toBeNull()
        ->and($reponse->json('data.elements.0.commande.reference'))->toBe($commande->reference);
});
