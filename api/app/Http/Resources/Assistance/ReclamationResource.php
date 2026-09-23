<?php

namespace App\Http\Resources\Assistance;

use App\Domain\Assistance\Models\Reclamation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reclamation */
class ReclamationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sejour' => $this->whenLoaded('sejour', fn () => [
                'reference' => $this->sejour->reference, 'logement' => $this->sejour->logement?->nom,
            ]),
            'client' => $this->whenLoaded('client', fn () => $this->client?->nomComplet()),
            'motif' => $this->motif,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'reponse' => $this->reponse,
            'avoir_montant' => $this->avoir_montant,
            'avoir_motif' => $this->avoir_motif,
            'fermee_le' => $this->fermee_le?->format('d/m/Y H:i:s'),
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
