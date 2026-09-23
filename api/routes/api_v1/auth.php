<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InscriptionController;
use Illuminate\Support\Facades\Route;

// Connexion unique pour tous les profils, inscription des clients.

Route::prefix('auth')->name('auth.')->group(function (): void {
    // 5 essais par minute et par adresse : freine la recherche de mots de passe et de codes.
    Route::middleware('throttle:5,1')->group(function (): void {
        Route::post('connexion', [AuthController::class, 'connexion'])->name('connexion');
        Route::post('verification', [InscriptionController::class, 'verifier'])->name('verification');
        Route::post('mot-de-passe/reinitialiser', [InscriptionController::class, 'reinitialiser'])->name('mot-de-passe.reinitialiser');
    });

    // Tout ce qui déclenche un courriel est limité plus sévèrement.
    Route::middleware('throttle:3,1')->group(function (): void {
        Route::post('inscription', [InscriptionController::class, 'inscrire'])->name('inscription');
        Route::post('verification/renvoyer', [InscriptionController::class, 'renvoyerLeCode'])->name('verification.renvoyer');
        Route::post('mot-de-passe/oublie', [InscriptionController::class, 'motDePasseOublie'])->name('mot-de-passe.oublie');
    });

    // Le jeton peut être expiré (mais encore rafraîchissable) : pas de garde ici,
    // c'est le rafraîchissement lui-même qui le contrôle.
    Route::post('rafraichir', [AuthController::class, 'rafraichir'])->name('rafraichir');

    Route::middleware('connecte')->group(function (): void {
        Route::get('moi', [AuthController::class, 'moi'])->name('moi');
        Route::post('deconnexion', [AuthController::class, 'deconnexion'])->name('deconnexion');
    });
});
