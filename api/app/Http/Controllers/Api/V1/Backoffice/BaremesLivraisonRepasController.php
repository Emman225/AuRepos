<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Repas\Models\BaremeLivraisonRepas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repas\BaremeLivraisonRepasRequest;
use App\Http\Resources\Repas\BaremeLivraisonRepasResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;

/** Barème de livraison repas › forfait par résidence (CdC — « Barème de livraison repas »). */
final class BaremesLivraisonRepasController extends Controller
{
    public function index(): JsonResponse
    {
        return ReponseApi::succes(BaremeLivraisonRepasResource::collection(
            BaremeLivraisonRepas::query()->with('residence')->orderBy('id')->get(),
        ));
    }

    public function creer(BaremeLivraisonRepasRequest $request): JsonResponse
    {
        $bareme = BaremeLivraisonRepas::create($request->validated());

        return ReponseApi::cree(new BaremeLivraisonRepasResource($bareme->load('residence')), 'Barème créé.');
    }

    public function modifier(BaremeLivraisonRepasRequest $request, BaremeLivraisonRepas $bareme): JsonResponse
    {
        $bareme->update($request->validated());

        return ReponseApi::succes(new BaremeLivraisonRepasResource($bareme->refresh()->load('residence')), 'Barème modifié.');
    }
}
