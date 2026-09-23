<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité HTTP sur toute réponse de l'API (CdC § 13.2 « HTTPS partout »).
 * L'API ne rend jamais de HTML : la CSP la plus stricte ne casse donc rien.
 */
final class EnTetesDeSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('X-Frame-Options', 'DENY');
        $reponse->headers->set('Referrer-Policy', 'no-referrer');
        $reponse->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        $reponse->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');
        $reponse->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if ($request->secure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $reponse;
    }
}
