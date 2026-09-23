<?php

use App\Http\Controllers\Api\V1\Proprietaire\LogementsController;
use App\Http\Controllers\Api\V1\Proprietaire\ResidencesController;
use App\Http\Controllers\Api\V1\Proprietaire\SejoursController;
use App\Http\Controllers\Api\V1\Proprietaire\TableauDeBordController;
use Illuminate\Support\Facades\Route;

// Espace propriétaire (self-service, lecture seule, CdC § 7). Un propriétaire connecté voit
// SES résidences, les logements qui s'y trouvent et les séjours qui s'y déroulent — jamais
// ceux d'un autre propriétaire : la résidence d'un autre N'EXISTE PAS pour lui (404, jamais 403).

Route::prefix('proprietaire')->name('proprietaire.')->middleware(['connecte', 'profil:proprietaire'])->group(function (): void {
    Route::get('tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');

    Route::get('residences', [ResidencesController::class, 'index'])->name('residences.index');
    Route::get('residences/{residence}/logements', [LogementsController::class, 'index'])->whereNumber('residence')->name('residences.logements');

    Route::get('logements/{logement}/sejours', [SejoursController::class, 'index'])->whereNumber('logement')->name('logements.sejours');
});
