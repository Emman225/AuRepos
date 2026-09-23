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
use App\Support\Api\ErreurMetier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function unSejourDuClient(?User $client = null): Sejour
{
    $client ??= User::factory()->profil(Profil::Client)->create();

    return Sejour::factory()->create(['client_id' => $client->id]);
}

// ---------------------------------------------------------------- commander : prix figé

it('fige le nom et le prix de vente de chaque ligne au moment de la commande', function (): void {
    $restaurateur = Restaurateur::factory()->avecPourcentage(50)->create();
    $produit = Produit::factory()->for($restaurateur)->create(['nom' => 'Attiéké poisson', 'prix_restaurateur' => 2000]);
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = unSejourDuClient($client);

    $commande = app(GestionDesCommandes::class)->commander(
        $sejour, $restaurateur, $client, [['produit_id' => $produit->id, 'quantite' => 2]], 'note_du_sejour',
    );

    // prix de vente = 2000 * 1.5 = 3000
    expect($commande->lignes)->toHaveCount(1)
        ->and($commande->lignes->first()->nom_produit)->toBe('Attiéké poisson')
        ->and($commande->lignes->first()->prix_unitaire_vente)->toBe(3000)
        ->and($commande->montant_total)->toBe(6000)
        ->and($commande->etat)->toBe(EtatDeCommande::Demande);

    // Le produit change ensuite : la commande déjà passée ne bouge pas.
    $produit->update(['prix_restaurateur' => 5000, 'nom' => 'Autre nom']);
    expect($commande->refresh()->lignes->first()->prix_unitaire_vente)->toBe(3000)
        ->and($commande->lignes->first()->nom_produit)->toBe('Attiéké poisson');
});

it('refuse une commande sur un produit indisponible', function (): void {
    $restaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $produit = Produit::factory()->for($restaurateur)->indisponible()->create();
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = unSejourDuClient($client);

    expect(fn () => app(GestionDesCommandes::class)->commander(
        $sejour, $restaurateur, $client, [['produit_id' => $produit->id, 'quantite' => 1]], 'note_du_sejour',
    ))->toThrow(ErreurMetier::class);
});

it('refuse une commande sur un produit d’un autre restaurateur', function (): void {
    $restaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $autreRestaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $produitAilleurs = Produit::factory()->for($autreRestaurateur)->create();
    $client = User::factory()->profil(Profil::Client)->create();
    $sejour = unSejourDuClient($client);

    expect(fn () => app(GestionDesCommandes::class)->commander(
        $sejour, $restaurateur, $client, [['produit_id' => $produitAilleurs->id, 'quantite' => 1]], 'note_du_sejour',
    ))->toThrow(ErreurMetier::class);
});

it('refuse une commande sur le séjour d’un autre client', function (): void {
    $restaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $produit = Produit::factory()->for($restaurateur)->create();
    $sejour = unSejourDuClient(); // appartient à un autre client
    $client = User::factory()->profil(Profil::Client)->create();

    expect(fn () => app(GestionDesCommandes::class)->commander(
        $sejour, $restaurateur, $client, [['produit_id' => $produit->id, 'quantite' => 1]], 'note_du_sejour',
    ))->toThrow(ErreurMetier::class);
});

// ---------------------------------------------------------------- cycle : confirmation, préparation, affectation, clôture

it('confirme une commande demandée', function (): void {
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Demande]);

    $commande = app(GestionDesCommandes::class)->confirmer($commande);

    expect($commande->etat)->toBe(EtatDeCommande::Confirmee);
});

it('la préparation enregistre le bon de préparation avec un écart entre commandé et servi', function (): void {
    $restaurateur = Restaurateur::factory()->avecPourcentage()->create();
    $commande = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::EnPreparation]);
    $ligne = $commande->lignes()->create([
        'produit_id' => Produit::factory()->for($restaurateur)->create()->id,
        'nom_produit' => 'Riz sauce', 'prix_unitaire_vente' => 2000, 'quantite_commandee' => 5,
    ]);

    $commande = app(GestionDesCommandes::class)->marquerPrete($commande, $restaurateur, [$ligne->id => 3]);

    expect($commande->etat)->toBe(EtatDeCommande::Prete)
        ->and($commande->lignes->first()->quantite_servie)->toBe(3);
});

it('refuse que le restaurateur d’une autre commande démarre sa préparation', function (): void {
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Confirmee]);
    $autreRestaurateur = Restaurateur::factory()->create();

    expect(fn () => app(GestionDesCommandes::class)->demarrerPreparation($commande, $autreRestaurateur))
        ->toThrow(ErreurMetier::class);
});

it('affecte un livreur, saisit sa rémunération et génère le code de livraison', function (): void {
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Prete]);
    $livreur = Livreur::factory()->create();

    $commande = app(GestionDesCommandes::class)->affecterUnLivreur($commande, $livreur, 1500);

    expect($commande->etat)->toBe(EtatDeCommande::EnLivraison)
        ->and($commande->livreur_id)->toBe($livreur->id)
        ->and($commande->remuneration_livreur)->toBe(1500)
        ->and(app(CodesSecrets::class)->existe($commande, 'livraison'))->toBeTrue();
});

it('clôture par code : seul le livreur affecté peut saisir le code', function (): void {
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Prete]);
    $livreur = Livreur::factory()->create();
    $unAutreLivreur = Livreur::factory()->create();
    $service = app(GestionDesCommandes::class);
    $commande = $service->affecterUnLivreur($commande, $livreur, 1000);
    $codeReel = app(CodesSecrets::class)->lirePourLeClient($commande, 'livraison');

    expect(fn () => $service->cloturerParCode($commande, (string) $codeReel, $unAutreLivreur->utilisateur))
        ->toThrow(ErreurMetier::class, 'Cette course ne vous est pas affectée.');

    $commande = $service->cloturerParCode($commande, (string) $codeReel, $livreur->utilisateur);
    expect($commande->etat)->toBe(EtatDeCommande::Livree);
});

it('clôture par code : un code erroné est refusé', function (): void {
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Prete]);
    $livreur = Livreur::factory()->create();
    $service = app(GestionDesCommandes::class);
    $commande = $service->affecterUnLivreur($commande, $livreur, 1000);

    expect(fn () => $service->cloturerParCode($commande, '000000', $livreur->utilisateur))
        ->toThrow(ErreurMetier::class);
    expect($commande->refresh()->etat)->toBe(EtatDeCommande::EnLivraison);
});

// ---------------------------------------------------------------- dette et gains

it('calcule la dette envers un restaurateur non assujetti à la TVA, sur les commandes livrées', function (): void {
    $restaurateur = Restaurateur::factory()->create(['assujetti_tva' => false]);
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 1000]);

    $livree = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::Livree]);
    $livree->lignes()->create(['produit_id' => $produit->id, 'nom_produit' => 'X', 'prix_unitaire_vente' => 1500, 'quantite_commandee' => 4, 'quantite_servie' => 3]);

    // Une commande seulement « prête » ne compte pas encore.
    $prete = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::Prete]);
    $prete->lignes()->create(['produit_id' => $produit->id, 'nom_produit' => 'X', 'prix_unitaire_vente' => 1500, 'quantite_commandee' => 10, 'quantite_servie' => 10]);

    $dette = app(GestionDesCommandes::class)->detteEnversLeRestaurateur($restaurateur);

    // 3 (quantité SERVIE, pas commandée) x 1000 (prix restaurateur, pas prix de vente) = 3000.
    expect($dette['du'])->toBe(3000)->and($dette['deja_verse'])->toBe(0);
});

it('ajoute la TVA à la dette d’un restaurateur assujetti', function (): void {
    $restaurateur = Restaurateur::factory()->create(['assujetti_tva' => true]);
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 1000]);
    $livree = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::Livree]);
    $livree->lignes()->create(['produit_id' => $produit->id, 'nom_produit' => 'X', 'prix_unitaire_vente' => 1500, 'quantite_commandee' => 2, 'quantite_servie' => 2]);

    $dette = app(GestionDesCommandes::class)->detteEnversLeRestaurateur($restaurateur);

    // 2 x 1000 = 2000, +18% TVA (taux par défaut) = 2360.
    expect($dette['du'])->toBe(2360);
});

it('calcule les gains d’un livreur sur ses courses livrées', function (): void {
    $livreur = Livreur::factory()->create();
    Commande::factory()->create(['etat' => EtatDeCommande::Livree, 'livreur_id' => $livreur->id, 'remuneration_livreur' => 1500]);
    Commande::factory()->create(['etat' => EtatDeCommande::Livree, 'livreur_id' => $livreur->id, 'remuneration_livreur' => 1000]);
    // En cours : ne compte pas encore.
    Commande::factory()->create(['etat' => EtatDeCommande::EnLivraison, 'livreur_id' => $livreur->id, 'remuneration_livreur' => 2000]);

    $gains = app(GestionDesCommandes::class)->gainsDuLivreur($livreur);

    expect($gains['total_gagne'])->toBe(2500)->and($gains['solde_du'])->toBe(2500);
});

// ---------------------------------------------------------------- back office : suivi

function connecterExploitationRepas(Profil $profil = Profil::Gestionnaire): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('le back office confirme, affecte un livreur et ne montre jamais le code de livraison', function (): void {
    connecterExploitationRepas();
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Demande]);

    test()->postJson("/api/v1/backoffice/commandes-repas/{$commande->id}/confirmer")
        ->assertOk()->assertJsonPath('data.etat', 'confirmee');

    $commande->update(['etat' => EtatDeCommande::Prete]);
    $livreur = Livreur::factory()->create();

    $reponse = test()->postJson("/api/v1/backoffice/commandes-repas/{$commande->id}/affecter-livreur", [
        'livreur_id' => $livreur->id, 'remuneration_livreur' => 1200,
    ])->assertOk();

    expect($reponse->json('data.code_livraison_emis'))->toBeTrue()
        ->and($reponse->json())->not->toHaveKey('data.code_livraison')
        ->and($reponse->getContent())->not->toContain('"code":');
});

it('le back office refuse une commande avec un motif', function (): void {
    connecterExploitationRepas();
    $commande = Commande::factory()->create(['etat' => EtatDeCommande::Demande]);

    test()->postJson("/api/v1/backoffice/commandes-repas/{$commande->id}/refuser", ['motif' => 'Restaurateur fermé'])
        ->assertOk()->assertJsonPath('data.etat', 'refusee');
});

it('filtre les commandes par état au back office', function (): void {
    connecterExploitationRepas();
    Commande::factory()->create(['etat' => EtatDeCommande::Demande]);
    Commande::factory()->create(['etat' => EtatDeCommande::Livree]);

    $reponse = test()->getJson('/api/v1/backoffice/commandes-repas?etat=livree')->assertOk();
    expect($reponse->json('data.elements'))->toHaveCount(1);
});
