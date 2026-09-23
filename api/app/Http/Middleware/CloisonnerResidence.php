<?php

namespace App\Http\Middleware;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Posée sur toute route dont un paramètre lié se résout à une résidence (directement,
 * via un logement, ou via un séjour) : un gestionnaire qui n'y est pas rattaché reçoit
 * un 404, jamais un 403 — comme pour le séjour d'un autre client, on ne révèle pas
 * qu'une résidence existe (CdC § 9.5).
 */
final class CloisonnerResidence
{
    public function __construct(private readonly PerimetreGestionnaire $perimetre) {}

    public function handle(Request $request, Closure $next): Response
    {
        $utilisateur = $request->user();
        $residenceId = match (true) {
            ! $utilisateur instanceof User => null,
            $request->route('residence') instanceof Residence => $request->route('residence')->id,
            $request->route('logement') instanceof Logement => $request->route('logement')->residence_id,
            $request->route('sejour') instanceof Sejour => $request->route('sejour')->logement->residence_id,
            default => null,
        };

        if ($residenceId !== null && $utilisateur instanceof User && ! $this->perimetre->residenceVisible($residenceId, $utilisateur)) {
            abort(404);
        }

        return $next($request);
    }
}
