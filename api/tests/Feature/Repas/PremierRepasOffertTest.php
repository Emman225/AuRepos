<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Produit;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterExploitationPremierRepas(Profil $profil = Profil::Gestionnaire): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

// -------------------------------------------------------- extra CdC § 5.2 « premier repas livré à l'arrivée »

it('offre le premier repas à l’arrivée : commande à zéro pour le client, offert = true', function (): void {
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Arrive]);
    // Pas de pourcentage plateforme validé : sans incidence, offert ignore prixDeVente().
    $restaurateur = Restaurateur::factory()->create();
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 2000]);

    $commande = app(GestionDesCommandes::class)->offrirLePremierRepas(
        $sejour, $restaurateur, [['produit_id' => $produit->id, 'quantite' => 2]],
    );

    expect($commande->offert)->toBeTrue()
        ->and($commande->montant_total)->toBe(0)
        ->and($commande->etat)->toBe(EtatDeCommande::Demande)
        ->and($commande->lignes->first()->prix_unitaire_vente)->toBe(0)
        ->and($commande->lignes->first()->quantite_commandee)->toBe(2);
});

it('refuse le premier repas offert avant le check-in', function (): void {
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Confirme]);
    $restaurateur = Restaurateur::factory()->create();
    $produit = Produit::factory()->for($restaurateur)->create();

    expect(fn () => app(GestionDesCommandes::class)->offrirLePremierRepas(
        $sejour, $restaurateur, [['produit_id' => $produit->id, 'quantite' => 1]],
    ))->toThrow(ErreurMetier::class, 'check-in');
});

it('le restaurateur du premier repas offert reste dû à son prix normal : la dette n’est pas affectée par le zéro client', function (): void {
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Arrive]);
    $restaurateur = Restaurateur::factory()->create(['assujetti_tva' => false]);
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 1500]);

    $service = app(GestionDesCommandes::class);
    $commande = $service->offrirLePremierRepas($sejour, $restaurateur, [['produit_id' => $produit->id, 'quantite' => 1]]);
    // Fait vivre la commande jusqu'à « livrée » avec la quantité servie = commandée, pour entrer dans la dette.
    $commande->update(['etat' => EtatDeCommande::Livree]);
    $commande->lignes->first()->update(['quantite_servie' => 1]);

    $dette = $service->detteEnversLeRestaurateur($restaurateur);

    // 1 x 1500 (prix restaurateur, jamais le prix de vente à zéro) = 1500.
    expect($dette['du'])->toBe(1500);
});

it('le back office déclenche le premier repas offert sur un séjour arrivé', function (): void {
    connecterExploitationPremierRepas();
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id, 'etat' => EtatDuSejour::Arrive]);
    $restaurateur = Restaurateur::factory()->create();
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 1000]);

    $reponse = test()->postJson('/api/v1/backoffice/commandes-repas/premier-repas-offert', [
        'sejour_id' => $sejour->id, 'restaurateur_id' => $restaurateur->id,
        'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
    ])->assertCreated();

    expect($reponse->json('data.offert'))->toBeTrue()
        ->and($reponse->json('data.montant_total'))->toBe(0);
});
