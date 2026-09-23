<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Repas\Models\Restaurateur;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\Repas\RestaurateurResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;

/**
 * Mon espace › Restaurateurs actifs et leur carte, pour savoir QUOI commander (CdC —
 * « Commander repas et boissons pendant le séjour »). Lecture seule, produits `disponible`
 * uniquement, jamais le prix restaurateur (prix d'achat).
 */
final class RestaurateursController extends Controller
{
    public function index(): JsonResponse
    {
        $restaurateurs = Restaurateur::query()->where('actif', true)->whereNotNull('pourcentage_plateforme')
            ->with(['produits' => fn ($q) => $q->where('disponible', true)])
            ->orderBy('id')->get();

        return ReponseApi::succes(RestaurateurResource::collection($restaurateurs));
    }
}
