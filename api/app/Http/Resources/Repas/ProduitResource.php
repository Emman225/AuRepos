<?php

namespace App\Http\Resources\Repas;

use App\Domain\Repas\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue INTERNE d'un produit (back office, restaurateur) : montre le prix restaurateur
 * (prix d'achat), jamais montré au client — voir App\Http\Resources\Client\Repas\ProduitResource.
 *
 * @mixin Produit
 */
class ProduitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurateur_id' => $this->restaurateur_id,
            'nom' => $this->nom,
            'description' => $this->description,
            'categorie' => $this->categorie->value,
            'prix_restaurateur' => $this->prix_restaurateur,
            'prix_vente' => $this->restaurateur?->pourcentage_plateforme !== null ? $this->prixDeVente() : null,
            'disponible' => $this->disponible,
        ];
    }
}
