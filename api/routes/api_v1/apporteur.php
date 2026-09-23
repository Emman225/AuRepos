<?php

use App\Http\Controllers\Api\V1\Apporteur\CommissionsController;
use App\Http\Controllers\Api\V1\Apporteur\FilleulsController;
use App\Http\Controllers\Api\V1\Apporteur\TableauDeBordController;
use Illuminate\Support\Facades\Route;

// Espace apporteur d'affaires (self-service, LECTURE SEULE — CdC, apporteurs). Un apporteur
// connecté voit SES filleuls, SES commissions et son solde dû, jamais ceux d'un autre : le
// reversement lui-même n'a pas de route ici, il passe par le décaissement générique de la
// Caisse (guichet « dettes partenaires »), comme pour un propriétaire.

Route::prefix('apporteur')->name('apporteur.')->middleware(['connecte', 'profil:apporteur'])->group(function (): void {
    Route::get('tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');
    Route::get('filleuls', [FilleulsController::class, 'index'])->name('filleuls.index');
    Route::get('commissions', [CommissionsController::class, 'index'])->name('commissions.index');
});
