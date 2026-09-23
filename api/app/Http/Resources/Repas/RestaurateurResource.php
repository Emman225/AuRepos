<?php

namespace App\Http\Resources\Repas;

use App\Domain\Repas\Models\Restaurateur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Restaurateur */
class RestaurateurResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actif' => $this->actif,
            'assujetti_tva' => $this->assujetti_tva,
            'pourcentage_plateforme' => $this->pourcentage_plateforme !== null ? (float) $this->pourcentage_plateforme : null,
            'compte' => [
                'id' => $this->utilisateur->id,
                'nom' => $this->utilisateur->nom,
                'prenoms' => $this->utilisateur->prenoms,
                'nom_complet' => $this->utilisateur->nomComplet(),
                'email' => $this->utilisateur->email,
                'telephone' => $this->utilisateur->telephone,
                'statut' => $this->utilisateur->statut->value,
            ],
        ];
    }
}
