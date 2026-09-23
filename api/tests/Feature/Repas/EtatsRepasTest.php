<?php

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Models\Produit;
use App\Domain\Repas\Models\Restaurateur;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function connecterExploitationEtatsRepas(Profil $profil = Profil::Gestionnaire): User
{
    auth('api')->forgetUser();
    auth('api')->unsetToken();
    $utilisateur = User::factory()->profil($profil)->create();
    test()->withToken(auth('api')->login($utilisateur));

    return $utilisateur;
}

it('additionne le CA et la marge des seules commandes livrées, quantité servie prioritaire', function (): void {
    connecterExploitationEtatsRepas();
    $restaurateur = Restaurateur::factory()->create();
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 1000]);

    // Livrée, écart commandé (5) / servi (3) : la marge doit retenir 3, pas 5.
    $livree = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::Livree, 'montant_total' => 4500]);
    $livree->lignes()->create([
        'produit_id' => $produit->id, 'nom_produit' => 'Riz sauce',
        'prix_unitaire_vente' => 1500, 'quantite_commandee' => 5, 'quantite_servie' => 3,
    ]);

    // Encore en préparation : ne doit compter ni dans le CA ni dans la marge.
    $enPreparation = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::EnPreparation, 'montant_total' => 3000]);
    $enPreparation->lignes()->create([
        'produit_id' => $produit->id, 'nom_produit' => 'Riz sauce', 'prix_unitaire_vente' => 1500, 'quantite_commandee' => 2,
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/etats/repas')->assertOk();

    // Ventes : 3 (servie) x 1500 = 4500. Coût restaurateur : 3 x 1000 = 3000. Marge : 1500.
    expect($reponse->json('data.nombre_de_commandes'))->toBe(2)
        ->and($reponse->json('data.ca_repas'))->toBe(4500)
        ->and($reponse->json('data.cout_restaurateurs'))->toBe(3000)
        ->and($reponse->json('data.marge_totale'))->toBe(1500)
        ->and($reponse->json('data.par_etat.livree'))->toBe(1)
        ->and($reponse->json('data.par_etat.en_preparation'))->toBe(1);
});

it('une commande offerte pèse sur la marge (coût réel) sans jamais gonfler le CA', function (): void {
    connecterExploitationEtatsRepas();
    $restaurateur = Restaurateur::factory()->create();
    $produit = Produit::factory()->for($restaurateur)->create(['prix_restaurateur' => 1200]);

    $offerte = Commande::factory()->for($restaurateur)->create(['etat' => EtatDeCommande::Livree, 'montant_total' => 0, 'offert' => true]);
    $offerte->lignes()->create([
        'produit_id' => $produit->id, 'nom_produit' => 'Attiéké', 'prix_unitaire_vente' => 0, 'quantite_commandee' => 1, 'quantite_servie' => 1,
    ]);

    $reponse = test()->getJson('/api/v1/backoffice/etats/repas')->assertOk();

    expect($reponse->json('data.nombre_de_repas_offerts'))->toBe(1)
        ->and($reponse->json('data.ca_repas'))->toBe(0)
        ->and($reponse->json('data.cout_restaurateurs'))->toBe(1200)
        ->and($reponse->json('data.marge_totale'))->toBe(-1200);
});

it('filtre les états repas par restaurateur', function (): void {
    connecterExploitationEtatsRepas();
    $restaurateurA = Restaurateur::factory()->create();
    $restaurateurB = Restaurateur::factory()->create();
    Commande::factory()->for($restaurateurA)->create(['etat' => EtatDeCommande::Livree]);
    Commande::factory()->for($restaurateurB)->create(['etat' => EtatDeCommande::Livree]);

    $reponse = test()->getJson("/api/v1/backoffice/etats/repas?restaurateur_id={$restaurateurA->id}")->assertOk();

    expect($reponse->json('data.nombre_de_commandes'))->toBe(1);
});
