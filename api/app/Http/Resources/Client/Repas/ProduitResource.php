<?php

namespace App\Http\Resources\Client\Repas;

use App\Domain\Repas\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Le client ne voit jamais le prix restaurateur (prix d'achat), seulement le prix de vente. @mixin Produit */
class ProduitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'description' => $this->description,
            'categorie' => $this->categorie->value,
            'prix' => $this->prixDeVente(),
            'disponible' => $this->disponible,
        ];
    }
}
