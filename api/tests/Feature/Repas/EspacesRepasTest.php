<?php

use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Models\Livreur;
use App\Domain\Repas\Models\Produit;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterEspaceRepas(User $utilisateur): void
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    test()->withToken(auth('api')->login($utilisateur));
}

// ---------------------------------------------------------------- espace client

it('le client voit les restaurateurs actifs avec pourcentage validé et leurs produits disponibles', function (): void {
    $client = User::factory()->profil(Profil::Client)->create();
    connecterEspaceRepas($client);

    $ok = Restaurateur::factory()->avecPourcentage(30)->create(['actif' => true]);
    Produit::factory()->for($ok)->create(['nom' => 'Kedjenou', 'disponible' => true, 'prix_restaurateur' => 2000]);
    Produit::factory()->for($ok)->indisponible()->create(['nom' => 'Épuisé']);

    // Pas encore de pourcentage validé : invisible pour le client.
    Restaurateur::factory()->create(['actif' => true]);
    // Inactif : invisible aussi.
    Restaurateur::factory()->avecPourcentage()->inactif()->create();

    $reponse = test()->getJson('/api/v1/client/restaurateurs')->assertOk();
    $donnees = $reponse->json('data');

    expect($donnees)->toHaveCount(1)
        ->and($donnees[0]['carte'])->toHaveCount(1)
        ->and($donnees[0]['carte'][0]['nom'])->toBe('Kedjenou')
        ->and($donnees[0]['carte'][0]['prix'])->toBe(2600) // 2000 * 1.3
        ->and($donnees[0]['carte'][0])->not->toHaveKey('prix_restaurateur');
});

it('le client commande, voit ses commandes, et jamais celles d’un autre client', function (): void {
    $client = User::factory()->profil(Profil::Client)->create();
    $autreClient = User::factory()->profil(Profil::Client)->create();
    $restaurateur = Restaurateur::factory()->avecPourcentage(20)->create();
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 1000]);
    $sejour = Sejour::factory()->create(['client_id' => $client->id]);
    $sejourAutrui = Sejour::factory()->create(['client_id' => $autreClient->id]);

    connecterEspaceRepas($client);

    // Le séjour d'un autre client N'EXISTE PAS pour moi.
    test()->postJson("/api/v1/client/sejours/{$sejourAutrui->reference}/commandes", [
        'restaurateur_id' => $restaurateur->id, 'mode_reglement' => 'note_du_sejour',
        'lignes' => [['produit_id' => $produit->id, 'quantite' => 1]],
    ])->assertNotFound();

    $reponse = test()->postJson("/api/v1/client/sejours/{$sejour->reference}/commandes", [
        'restaurateur_id' => $restaurateur->id, 'mode_reglement' => 'note_du_sejour',
        'lignes' => [['produit_id' => $produit->id, 'quantite' => 2]],
    ])->assertCreated();

    expect($reponse->json('data.montant_total'))->toBe(2400); // 1000 * 1.2 * 2

    $mesCommandes = test()->getJson("/api/v1/client/sejours/{$sejour->reference}/commandes")->assertOk()->json('data');
    expect($mesCommandes)->toHaveCount(1);
});

it('le code de livraison n’apparaît au client que lorsque la commande est en livraison, et à lui seul', function (): void {
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = Sejour::factory()->create(['client_id' => $client->id]);
    $restaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $commandeEnCours = Commande::factory()->for($restaurateur)->create(['sejour_id' => $sejour->id, 'etat' => EtatDeCommande::Confirmee]);
    $commandeEnLivraison = Commande::factory()->for($restaurateur)->create(['sejour_id' => $sejour->id, 'etat' => EtatDeCommande::Prete]);
    $livreur = Livreur::factory()->create();
    app(GestionDesCommandes::class)->affecterUnLivreur($commandeEnLivraison, $livreur, 1000);

    connecterEspaceRepas($client);

    $repEnCours = test()->getJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commandeEnCours->id}")->assertOk();
    expect($repEnCours->json('data.code_livraison'))->toBeNull();

    $repEnLivraison = test()->getJson("/api/v1/client/sejours/{$sejour->reference}/commandes/{$commandeEnLivraison->id}")->assertOk();
    expect($repEnLivraison->json('data.code_livraison'))->not->toBeNull()->toHaveLength(6);
});

// ---------------------------------------------------------------- espace restaurateur

it('le restaurateur ne voit et ne gère que sa propre carte et ses propres commandes', function (): void {
    $restaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $autreRestaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $monProduit = Produit::factory()->for($restaurateur)->create();
    $produitAutrui = Produit::factory()->for($autreRestaurateur)->create();
    $maCommande = Commande::factory()->for($restaurateur)->create();
    $commandeAutrui = Commande::factory()->for($autreRestaurateur)->create();

    connecterEspaceRepas($restaurateur->utilisateur);

    $produits = test()->getJson('/api/v1/restaurateur/produits')->assertOk()->json('data');
    expect($produits)->toHaveCount(1)->and($produits[0]['id'])->toBe($monProduit->id);

    // Le produit d'un autre restaurateur N'EXISTE PAS pour moi.
    test()->putJson("/api/v1/restaurateur/produits/{$produitAutrui->id}", ['nom' => 'Piraté'])->assertNotFound();

    $commandes = test()->getJson('/api/v1/restaurateur/commandes')->assertOk()->json('data');
    expect($commandes)->toHaveCount(1)->and($commandes[0]['id'])->toBe($maCommande->id);

    test()->getJson("/api/v1/restaurateur/commandes/{$commandeAutrui->id}")->assertNotFound();
});

it('le restaurateur démarre la préparation et marque prête avec le bon de préparation', function (): void {
    $restaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $commande = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::Confirmee]);
    $produit = Produit::factory()->for($restaurateur)->create();
    $ligne = $commande->lignes()->create(['produit_id' => $produit->id, 'nom_produit' => 'Riz', 'prix_unitaire_vente' => 1000, 'quantite_commandee' => 4]);

    connecterEspaceRepas($restaurateur->utilisateur);

    test()->postJson("/api/v1/restaurateur/commandes/{$commande->id}/preparation")->assertOk()->assertJsonPath('data.etat', 'en_preparation');

    $reponse = test()->postJson("/api/v1/restaurateur/commandes/{$commande->id}/prete", [
        'quantites_servies' => [$ligne->id => 3],
    ])->assertOk();

    expect($reponse->json('data.etat'))->toBe('prete')
        ->and($reponse->json('data.lignes.0.quantite_servie'))->toBe(3)
        ->and($reponse->getContent())->not->toContain('"code_livraison"');
});

// ---------------------------------------------------------------- espace livreur

it('le livreur ne voit que ses courses, jamais celles d’un autre livreur, et clôture par code', function (): void {
    $livreur = Livreur::factory()->create();
    $autreLivreur = Livreur::factory()->create();
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Prete]);
    $commandeAutrui = Commande::factory()->create(['etat' => EtatDeCommande::Prete]);
    app(GestionDesCommandes::class)->affecterUnLivreur($commande, $livreur, 1200);
    app(GestionDesCommandes::class)->affecterUnLivreur($commandeAutrui, $autreLivreur, 800);

    connecterEspaceRepas($livreur->utilisateur);

    $courses = test()->getJson('/api/v1/livreur/courses')->assertOk()->json('data');
    expect($courses)->toHaveCount(1)->and($courses[0]['id'])->toBe($commande->id);

    // La course d'un autre livreur N'EXISTE PAS pour moi.
    test()->getJson("/api/v1/livreur/courses/{$commandeAutrui->id}")->assertNotFound();
    test()->postJson("/api/v1/livreur/courses/{$commandeAutrui->id}/cloture", ['code' => '123456'])->assertNotFound();

    // Aucune route ne lui renvoie jamais le code en clair : il doit le SAISIR.
    $affichage = test()->getJson("/api/v1/livreur/courses/{$commande->id}")->assertOk();
    expect($affichage->getContent())->not->toContain('"code_livraison"');

    $codeReel = app(CodesSecrets::class)->lirePourLeClient($commande, 'livraison');

    test()->postJson("/api/v1/livreur/courses/{$commande->id}/cloture", ['code' => 'faux12'])->assertStatus(422);

    test()->postJson("/api/v1/livreur/courses/{$commande->id}/cloture", ['code' => (string) $codeReel])
        ->assertOk()->assertJsonPath('data.etat', 'livree');

    expect(test()->getJson('/api/v1/livreur/gains')->assertOk()->json('data.total_gagne'))->toBe(1200);
});
