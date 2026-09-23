<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Proprietaire\SejourResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace propriétaire › Séjours d'un de SES logements (lecture seule, CdC § 7). */
final class SejoursController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function index(Request $request, Logement $logement): JsonResponse
    {
        // La résidence d'un autre propriétaire N'EXISTE PAS pour moi : 404, jamais 403.
        if ($logement->residence->proprietaire_id !== $this->monProprietaire($request)->id) {
            abort(404);
        }

        return ReponseApi::succes(SejourResource::collection(
            Sejour::query()->where('logement_id', $logement->id)->with('client')->orderByDesc('arrivee')->get(),
        ));
    }
}
