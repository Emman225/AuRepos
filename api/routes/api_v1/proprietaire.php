<?php

use App\Http\Controllers\Api\V1\Proprietaire\BonsController;
use App\Http\Controllers\Api\V1\Proprietaire\DemandesPaiementController;
use App\Http\Controllers\Api\V1\Proprietaire\DetteController;
use App\Http\Controllers\Api\V1\Proprietaire\LogementsController;
use App\Http\Controllers\Api\V1\Proprietaire\NegociationController;
use App\Http\Controllers\Api\V1\Proprietaire\PhotosController;
use App\Http\Controllers\Api\V1\Proprietaire\RelevesController;
use App\Http\Controllers\Api\V1\Proprietaire\ResidencesController;
use App\Http\Controllers\Api\V1\Proprietaire\SejoursController;
use App\Http\Controllers\Api\V1\Proprietaire\TableauDeBordController;
use Illuminate\Support\Facades\Route;

// Espace propriétaire (self-service, CdC § 7). Un propriétaire connecté voit et gère SES
// résidences, les logements qui s'y trouvent et les séjours qui s'y déroulent — jamais ceux
// d'un autre propriétaire : la résidence d'un autre N'EXISTE PAS pour lui (404, jamais 403).

Route::prefix('proprietaire')->name('proprietaire.')->middleware(['connecte', 'profil:proprietaire'])->group(function (): void {
    Route::get('tableau-de-bord', [TableauDeBordController::class, 'index'])->name('tableau-de-bord');

    // Résidences : création par le propriétaire lui-même (CdC § 7.1), bouton « Occupée / Disponible ».
    Route::prefix('residences')->name('residences.')->group(function (): void {
        Route::get('/', [ResidencesController::class, 'index'])->name('index');
        Route::post('/', [ResidencesController::class, 'creer'])->name('creer');
        Route::get('{residence}', [ResidencesController::class, 'afficher'])->whereNumber('residence')->name('afficher');
        Route::put('{residence}', [ResidencesController::class, 'modifier'])->whereNumber('residence')->name('modifier');
        Route::put('{residence}/disponibilite', [ResidencesController::class, 'basculerLaDisponibilite'])->whereNumber('residence')->name('disponibilite');
        Route::get('{residence}/disponibilite/historique', [ResidencesController::class, 'historiqueDisponibilite'])->whereNumber('residence')->name('disponibilite.historique');

        Route::prefix('{residence}/logements')->name('logements.')->scopeBindings()->group(function (): void {
            Route::get('/', [LogementsController::class, 'index'])->name('index');
            Route::post('/', [LogementsController::class, 'creer'])->name('creer');
            Route::get('{logement}', [LogementsController::class, 'afficher'])->whereNumber('logement')->name('afficher');
            Route::put('{logement}', [LogementsController::class, 'modifier'])->whereNumber('logement')->name('modifier');
            Route::post('{logement}/soumission', [LogementsController::class, 'soumettre'])->whereNumber('logement')->name('soumettre');
            Route::get('{logement}/publication', [LogementsController::class, 'publication'])->whereNumber('logement')->name('publication');

            Route::get('{logement}/sejours', [SejoursController::class, 'index'])->whereNumber('logement')->name('sejours');

            // Négociation du prix propriétaire (CdC § 7.2, P3-PUB-03).
            Route::get('{logement}/negociation', [NegociationController::class, 'afficher'])->whereNumber('logement')->name('negociation.afficher');
            Route::post('{logement}/negociation', [NegociationController::class, 'proposer'])->whereNumber('logement')->name('negociation.proposer');

            Route::prefix('{logement}/photos')->name('photos.')->group(function (): void {
                Route::get('/', [PhotosController::class, 'index'])->name('index');
                Route::post('/', [PhotosController::class, 'ajouter'])->name('ajouter');
                Route::put('ordre', [PhotosController::class, 'reordonner'])->name('reordonner');
                Route::put('{photo}/couverture', [PhotosController::class, 'definirCouverture'])->whereNumber('photo')->name('couverture');
                Route::delete('{photo}', [PhotosController::class, 'supprimer'])->whereNumber('photo')->name('supprimer');
            });
        });
    });

    // Compatibilité : ancienne route à plat (déjà consommée par le web, cf. api.ts), conservée telle quelle.
    Route::get('logements/{logement}/sejours', [SejoursController::class, 'index'])->whereNumber('logement')->name('logements.sejours');

    // Bons de mise à disposition (CdC § 7.2, P3-PRO-01) : validation manuelle si le mandat ne l'automatise pas.
    Route::prefix('bons')->name('bons.')->group(function (): void {
        Route::get('/', [BonsController::class, 'index'])->name('index');
        Route::put('{bon}/validation', [BonsController::class, 'valider'])->whereNumber('bon')->name('valider');
    });

    // Dette, relevés et demandes de paiement (CdC § 7.2 et § 8.7, P3-PRO-02 à 04).
    Route::get('dette', [DetteController::class, 'index'])->name('dette');

    Route::prefix('releves')->name('releves.')->group(function (): void {
        Route::get('/', [RelevesController::class, 'index'])->name('index');
        Route::get('{releve}/telechargement', [RelevesController::class, 'telecharger'])->whereNumber('releve')->name('telecharger');
        Route::get('{releve}/attestation', [RelevesController::class, 'telechargerAttestation'])->whereNumber('releve')->name('attestation');
    });

    Route::prefix('demandes-paiement')->name('demandes-paiement.')->group(function (): void {
        Route::get('/', [DemandesPaiementController::class, 'index'])->name('index');
        Route::post('/', [DemandesPaiementController::class, 'creer'])->name('creer');
    });
});
