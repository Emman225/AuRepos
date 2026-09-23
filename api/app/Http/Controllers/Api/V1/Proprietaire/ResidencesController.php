<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Proprietaire\ResidenceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace propriétaire › Mes résidences (lecture seule, CdC § 7). */
final class ResidencesController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function index(Request $request): JsonResponse
    {
        $residences = $this->monProprietaire($request)->residences()
            ->with('quartier.commune')
            ->withCount('logements')
            ->orderBy('nom')
            ->get();

        return ReponseApi::succes(ResidenceResource::collection($residences));
    }
}
