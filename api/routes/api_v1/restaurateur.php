<?php

use App\Http\Controllers\Api\V1\Restaurateur\CommandesController;
use App\Http\Controllers\Api\V1\Restaurateur\DetteController;
use App\Http\Controllers\Api\V1\Restaurateur\ProduitsController;
use App\Http\Controllers\Api\V1\Restaurateur\TableauDeBordController;
use Illuminate\Support\Facades\Route;

// Espace restaurateur (self-service, CdC — « Repas et boissons », espace restaurateur :
// « carte, stock, bons de préparation, dette, paiements »). Un restaurateur connecté ne
// voit et ne touche que SA carte et SES commandes, jamais celles d'un autre.

Route::prefix('restaurateur')->name('restaurateur.')->middleware(['connecte', 'profil:restaurateur'])->group(function (): void {
    Route::get('tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');

    Route::get('produits', [ProduitsController::class, 'index'])->name('produits.index');
    Route::post('produits', [ProduitsController::class, 'creer'])->name('produits.creer');
    Route::put('produits/{produit}', [ProduitsController::class, 'modifier'])->whereNumber('produit')->name('produits.modifier');

    Route::get('commandes', [CommandesController::class, 'index'])->name('commandes.index');
    Route::get('commandes/{commande}', [CommandesController::class, 'afficher'])->whereNumber('commande')->name('commandes.afficher');
    Route::post('commandes/{commande}/preparation', [CommandesController::class, 'demarrerPreparation'])->whereNumber('commande')->name('commandes.preparation');
    // Le bon de préparation : la quantité RÉELLEMENT servie par ligne (CdC, circuit vente→livraison).
    Route::post('commandes/{commande}/prete', [CommandesController::class, 'marquerPrete'])->whereNumber('commande')->name('commandes.prete');

    Route::get('dette', [DetteController::class, 'index'])->name('dette');
});
