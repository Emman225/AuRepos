<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Transferts\Models\Chauffeur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Chauffeur */
class ChauffeurResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actif' => $this->actif,
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
