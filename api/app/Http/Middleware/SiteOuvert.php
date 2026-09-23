<?php

namespace App\Http\Middleware;

use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Mode « site en construction » (CdC § 12, réservé au super administrateur).
 * Posé sur les routes du site public et de l'espace client : elles répondent
 * 503 tant que le mode est actif. Le personnel continue de travailler, et la
 * connexion reste ouverte pour qu'il puisse entrer.
 */
final class SiteOuvert
{
    public function __construct(private readonly Parametres $parametres) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->parametres->valeur('general.site_en_construction') === true) {
            $utilisateur = auth('api')->user();

            if (! $utilisateur instanceof User || ! $utilisateur->profil->estPersonnel()) {
                throw new HttpException(503);
            }
        }

        return $next($request);
    }
}
