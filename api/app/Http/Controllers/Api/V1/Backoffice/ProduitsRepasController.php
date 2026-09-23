<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Repas\Models\Produit;
use App\Domain\Repas\Models\Restaurateur;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repas\ProduitRequest;
use App\Http\Resources\Repas\ProduitResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;

/**
 * Carte d'un restaurateur, gérée par le back office (CdC — « Plats et boissons par
 * restaurateur »). Un administrateur ou un gestionnaire peut gérer la carte au nom du
 * restaurateur, en plus de son propre espace self-service.
 */
final class ProduitsRepasController extends Controller
{
    public function index(Restaurateur $restaurateur): JsonResponse
    {
        return ReponseApi::succes(ProduitResource::collection(
            $restaurateur->produits()->with('restaurateur')->orderBy('nom')->get(),
        ));
    }

    public function creer(ProduitRequest $request, Restaurateur $restaurateur): JsonResponse
    {
        $produit = $restaurateur->produits()->create($request->validated());

        return ReponseApi::cree(new ProduitResource($produit->load('restaurateur')), 'Produit ajouté à la carte.');
    }

    public function modifier(ProduitRequest $request, Restaurateur $restaurateur, Produit $produit): JsonResponse
    {
        $this->verifierAppartenance($restaurateur, $produit);

        $produit->update($request->validated());

        return ReponseApi::succes(new ProduitResource($produit->refresh()->load('restaurateur')), 'Produit modifié.');
    }

    private function verifierAppartenance(Restaurateur $restaurateur, Produit $produit): void
    {
        if ($produit->restaurateur_id !== $restaurateur->id) {
            abort(404);
        }
    }
}
