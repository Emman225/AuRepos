<?php

namespace App\Providers;

use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\PaiementEnLigne\Contracts\PasserelleDePaiement;
use App\Domain\PaiementEnLigne\Passerelles\PasserelleDEssai;
use App\Domain\PaiementEnLigne\Passerelles\PaySecure;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Une seule instance par requête : les valeurs ne sont lues en base qu'une fois.
        $this->app->singleton(Parametres::class);

        // La passerelle de paiement est choisie par configuration : « essai » en développement,
        // « paysecure » en production. Le reste du code ne connaît que le contrat.
        $this->app->singleton(PasserelleDePaiement::class, fn () => match ((string) config('paiement.passerelle')) {
            'paysecure' => new PaySecure,
            default => new PasserelleDEssai,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Noms stables des titulaires de pièces : un renommage de classe ne casse pas les données.
        // Les écouteurs d’app/Listeners sont découverts automatiquement par Laravel (une méthode handle()
        // typée par son événement suffit). Les déclarer ici EN PLUS les enregistrerait deux fois :
        // un reçu part une seule fois grâce à son garde-fou, mais des points seraient attribués en double.

        Relation::morphMap([
            'proprietaire' => Proprietaire::class, 'logement' => Logement::class, 'sejour' => Sejour::class,
            'reclamation' => Reclamation::class,
        ]);

        // Limite de débit générale posée sur TOUTE l'API (P1-API-08) : par utilisateur connecté,
        // par adresse IP sinon. Vient EN PLUS des throttle:x,1 déjà posés route par route sur les
        // opérations sensibles (connexion, paiement...) — celles-ci restent plus strictes.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute($request->user() ? 300 : 60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
