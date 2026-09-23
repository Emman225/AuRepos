<?php

namespace App\Http\Controllers\Api\V1\Restaurateur;

use App\Domain\Repas\Models\Produit;
use App\Http\Controllers\Api\V1\Restaurateur\Concerns\ResoutLeRestaurateurConnecte;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repas\ProduitRequest;
use App\Http\Resources\Repas\ProduitResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace restaurateur › SA carte (CdC — espace restaurateur : « carte, stock… »). */
final class ProduitsController extends Controller
{
    use ResoutLeRestaurateurConnecte;

    public function index(Request $request): JsonResponse
    {
        $restaurateur = $this->monRestaurateur($request);

        return ReponseApi::succes(ProduitResource::collection(
            $restaurateur->produits()->with('restaurateur')->orderBy('nom')->get(),
        ));
    }

    public function creer(ProduitRequest $request): JsonResponse
    {
        $restaurateur = $this->monRestaurateur($request);
        $produit = $restaurateur->produits()->create($request->validated());

        return ReponseApi::cree(new ProduitResource($produit->load('restaurateur')), 'Produit ajouté à votre carte.');
    }

    public function modifier(ProduitRequest $request, Produit $produit): JsonResponse
    {
        // Le produit d'un autre restaurateur N'EXISTE PAS pour moi : 404, jamais 403.
        if ($produit->restaurateur_id !== $this->monRestaurateur($request)->id) {
            abort(404);
        }

        $produit->update($request->validated());

        return ReponseApi::succes(new ProduitResource($produit->refresh()->load('restaurateur')), 'Produit modifié.');
    }
}
