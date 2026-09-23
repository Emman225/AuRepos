<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Partenaires\Services\DetteProprietaire;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace propriétaire › SA dette et SES paiements déjà reçus (CdC § 10, P3-PRO-02). */
final class DetteController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function index(Request $request, DetteProprietaire $dette): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);

        $paiements = Reglement::query()
            ->where('tiers_id', $proprietaire->user_id)->where('sens', 'decaissement')->where('etat', EtatDuReglement::Effectue)
            ->orderByDesc('finalise_le')->get();

        return ReponseApi::succes([
            ...$dette->soldeDu($proprietaire),
            'paiements' => $paiements->map(fn (Reglement $r): array => [
                'reference' => $r->reference, 'montant' => $r->montant, 'mode' => $r->mode->libelle(), 'date' => $r->finalise_le?->format('d/m/Y'),
            ]),
        ]);
    }
}
