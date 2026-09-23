<?php

use App\Http\Controllers\Api\V1\Chauffeur\GainsController;
use App\Http\Controllers\Api\V1\Chauffeur\TableauDeBordController;
use App\Http\Controllers\Api\V1\Chauffeur\TransfertsController;
use App\Http\Controllers\Api\V1\Chauffeur\VehiculesController;
use Illuminate\Support\Facades\Route;

// Espace chauffeur (self-service, CdC § 6.6). Un chauffeur connecté voit SES transferts et
// SES véhicules, jamais ceux d'un autre : le transfert d'un autre chauffeur N'EXISTE PAS pour
// lui (404, jamais 403). Il SAISIT le code de prise en charge, il ne le lit jamais (CdC § 11).

Route::prefix('chauffeur')->name('chauffeur.')->middleware(['connecte', 'profil:chauffeur'])->group(function (): void {
    Route::get('tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');

    Route::get('transferts', [TransfertsController::class, 'index'])->name('transferts.index');
    Route::post('transferts/{transfert}/cloturer', [TransfertsController::class, 'cloturer'])->whereNumber('transfert')->name('transferts.cloturer');

    Route::get('gains', [GainsController::class, 'index'])->name('gains');

    Route::get('vehicules', [VehiculesController::class, 'index'])->name('vehicules.index');
    Route::post('vehicules', [VehiculesController::class, 'creer'])->name('vehicules.creer');
});
