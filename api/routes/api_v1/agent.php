<?php

use App\Http\Controllers\Api\V1\Agent\EtatsDesLieuxController;
use App\Http\Controllers\Api\V1\Agent\MissionsController;
use App\Http\Controllers\Api\V1\Agent\SejoursController;
use Illuminate\Support\Facades\Route;

// Espace agent de terrain (self-service, CdC § 6.3) : check-in, fiche de police, état des
// lieux, check-out. Pas de fiche métier dédiée (contrairement au chauffeur ou au
// restaurateur) : un compte `User` avec `Profil::AgentTerrain` suffit. L'agent SAISIT le
// code d'arrivée, il ne le lit jamais (CdC § 11).

Route::prefix('agent')->name('agent.')->middleware(['connecte', 'profil:agent_terrain'])->group(function (): void {
    Route::get('sejours', [SejoursController::class, 'index'])->name('sejours.index');
    Route::get('sejours/{sejour}', [SejoursController::class, 'afficher'])->whereNumber('sejour')->name('sejours.afficher');

    Route::get('sejours/{sejour}/occupants', [SejoursController::class, 'occupants'])->whereNumber('sejour')->name('sejours.occupants');
    Route::post('sejours/{sejour}/occupants/{occupant}/piece', [SejoursController::class, 'deposerLaPieceDUnOccupant'])->whereNumber('sejour')->whereNumber('occupant')->name('sejours.occupants.piece');

    Route::post('sejours/{sejour}/check-in', [SejoursController::class, 'checkIn'])->whereNumber('sejour')->name('sejours.check-in');
    Route::get('sejours/{sejour}/consommations', [SejoursController::class, 'consommations'])->whereNumber('sejour')->name('sejours.consommations');
    Route::post('sejours/{sejour}/check-out', [SejoursController::class, 'checkOutSejour'])->whereNumber('sejour')->name('sejours.check-out');

    // États des lieux d'entrée et de sortie (P2-SEJ-02). Pas de scopeBindings() : le nom
    // français de la relation (`etatsDesLieux`) ne suit pas la pluralisation anglaise que
    // Laravel devine du paramètre — l'appartenance au séjour est donc vérifiée dans le contrôleur.
    Route::prefix('sejours/{sejour}/etats-des-lieux')->name('sejours.etats-des-lieux.')->group(function (): void {
        Route::get('/', [EtatsDesLieuxController::class, 'index'])->name('index');
        Route::post('/', [EtatsDesLieuxController::class, 'etablir'])->name('etablir');
        Route::get('pdf', [EtatsDesLieuxController::class, 'pdf'])->name('pdf');
        Route::post('{etatDesLieu}/lignes', [EtatsDesLieuxController::class, 'ajouterUneLigne'])->whereNumber('etatDesLieu')->name('lignes.ajouter');
        Route::post('{etatDesLieu}/lignes/{ligne}/photos', [EtatsDesLieuxController::class, 'ajouterUnePhoto'])->whereNumber('etatDesLieu')->whereNumber('ligne')->name('lignes.photos.ajouter');
        Route::post('{etatDesLieu}/signature', [EtatsDesLieuxController::class, 'signer'])->whereNumber('etatDesLieu')->name('signer');
    });

    // Mes missions de ménage (P2-MEN-01, CdC § 6.4) : celles qui me sont affectées ;
    // seul le déroulement (démarrer / terminer) revient à l'agent, l'affectation au back office.
    Route::prefix('missions')->name('missions.')->group(function (): void {
        Route::get('/', [MissionsController::class, 'index'])->name('index');
        Route::post('{mission}/debut', [MissionsController::class, 'demarrer'])->whereNumber('mission')->name('debut');
        Route::post('{mission}/fin', [MissionsController::class, 'terminer'])->whereNumber('mission')->name('fin');
    });
});
