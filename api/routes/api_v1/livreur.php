<?php

use App\Http\Controllers\Api\V1\Livreur\CoursesController;
use App\Http\Controllers\Api\V1\Livreur\GainsController;
use App\Http\Controllers\Api\V1\Livreur\TableauDeBordController;
use Illuminate\Support\Facades\Route;

// Espace livreur (self-service, CdC — « Reçoit ses courses de repas, récupère la commande
// chez le restaurateur, saisit le code de livraison du client, consulte ses gains »).
// Le code n'est JAMAIS exposé ici : le livreur le SAISIT, il ne le lit jamais (CdC § 11).

Route::prefix('livreur')->name('livreur.')->middleware(['connecte', 'profil:livreur'])->group(function (): void {
    Route::get('tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');

    Route::get('courses', [CoursesController::class, 'index'])->name('courses.index');
    Route::get('courses/{commande}', [CoursesController::class, 'afficher'])->whereNumber('commande')->name('courses.afficher');
    Route::post('courses/{commande}/cloture', [CoursesController::class, 'cloturer'])->whereNumber('commande')->name('courses.cloturer');

    Route::get('gains', [GainsController::class, 'index'])->name('gains');
});
