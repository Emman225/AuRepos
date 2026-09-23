<?php

namespace App\Http\Controllers\Api\V1\Restaurateur;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Http\Controllers\Api\V1\Restaurateur\Concerns\ResoutLeRestaurateurConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace restaurateur › SA dette et SES paiements déjà reçus (CdC — espace restaurateur : « dette, paiements »). */
final class DetteController extends Controller
{
    use ResoutLeRestaurateurConnecte;

    public function index(Request $request, GestionDesCommandes $commandes): JsonResponse
    {
        $restaurateur = $this->monRestaurateur($request);

        $paiements = Reglement::query()
            ->where('tiers_id', $restaurateur->user_id)->where('sens', 'decaissement')->where('etat', EtatDuReglement::Effectue)
            ->orderByDesc('finalise_le')->get();

        return ReponseApi::succes([
            ...$commandes->detteEnversLeRestaurateur($restaurateur),
            'paiements' => $paiements->map(fn (Reglement $r): array => [
                'reference' => $r->reference, 'montant' => $r->montant, 'mode' => $r->mode->libelle(), 'date' => $r->finalise_le?->format('d/m/Y'),
            ]),
        ]);
    }
}
