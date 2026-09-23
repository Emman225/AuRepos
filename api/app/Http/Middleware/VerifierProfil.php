<?php

namespace App\Http\Middleware;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloisonnement par profil, posé sur les GROUPES de routes :
 *
 *   Route::middleware(['auth:api', 'profil:administrateur,gestionnaire'])->group(…)
 *
 * Principe repris de Mon Gravier : « masquer une entrée de menu n'est pas
 * une protection » — celui qui tape l'adresse doit être refusé ici, côté
 * serveur. Le compte est relu en base à chaque requête : un compte bloqué
 * ou dont le profil a changé perd ses accès sans attendre la fin de son jeton.
 */
final class VerifierProfil
{
    public function handle(Request $request, Closure $next, string ...$profils): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur instanceof User) {
            throw new AuthenticationException;
        }

        if (! $utilisateur->statut->peutSeConnecter()) {
            throw new AuthenticationException('Ce compte ne peut plus se connecter.');
        }

        $autorises = array_map(static fn (string $p): Profil => Profil::from($p), $profils);

        if (! in_array($utilisateur->profil, $autorises, true)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
