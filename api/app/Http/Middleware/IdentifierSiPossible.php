<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Identifie le porteur d'un jeton s'il y en a un, sans JAMAIS refuser un visiteur anonyme.
 * Sert aux pages publiques qui en disent plus à un client connecté : l'estimation d'un séjour
 * y ajoute les points de fidélité utilisables.
 */
final class IdentifierSiPossible
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            auth('api')->user();
        } catch (Throwable) {
            // Jeton absent, expiré ou invalide : on continue en visiteur.
        }

        return $next($request);
    }
}
