<?php

namespace App\Http\Middleware;

use App\Domain\Comptes\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second contrôle de toute route connectée (groupe `connecte`), après la
 * validité du jeton : le compte est-il toujours en droit de s'en servir ?
 *
 *   - un compte bloqué perd ses accès tout de suite, sans attendre que son jeton expire ;
 *   - un jeton émis AVANT un changement de mot de passe ne vaut plus rien :
 *     celui qui a volé une session la perd quand la victime change son mot de passe.
 */
final class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur instanceof User || ! $utilisateur->statut->peutSeConnecter()) {
            throw new AuthenticationException('Ce compte ne peut plus se connecter.');
        }

        $garde = auth('api');
        $emisLe = $garde instanceof JWTGuard ? (int) $garde->payload()->get('iat') : 0;
        $changeLe = $utilisateur->mot_de_passe_change_le;

        if ($changeLe !== null && $emisLe < $changeLe->getTimestamp()) {
            throw new AuthenticationException('Votre mot de passe a changé. Reconnectez-vous.');
        }

        return $next($request);
    }
}
