<?php

use App\Http\Middleware\CloisonnerResidence;
use App\Http\Middleware\CompteActif;
use App\Http\Middleware\EnTetesDeSecurite;
use App\Http\Middleware\SiteOuvert;
use App\Http\Middleware\VerifierProfil;
use App\Support\Api\RenduDesExceptions;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Un fichier de routes par espace (profil), tous sous /api/v1.
            // Le cloisonnement par profil se pose sur le GROUPE, jamais
            // contrôleur par contrôleur.
            Route::middleware(['api', 'throttle:api', EnTetesDeSecurite::class])
                ->prefix('api/v1')
                ->name('api.v1.')
                ->group(function (): void {
                    foreach (glob(__DIR__.'/../routes/api_v1/*.php') ?: [] as $fichier) {
                        require $fichier;
                    }
                });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['profil' => VerifierProfil::class, 'site.ouvert' => SiteOuvert::class, 'cloisonner.residence' => CloisonnerResidence::class]);
        // Toute route connectée : jeton valide PUIS compte toujours en droit de s'en servir.
        $middleware->group('connecte', ['auth:api', CompteActif::class]);
        // API sans page de connexion : un visiteur non connecté reçoit un 401, jamais une redirection.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        RenduDesExceptions::enregistrer($exceptions);
    })->create();
