<?php

namespace App\Http\Resources\Sejours;

use App\Domain\Sejours\Models\EtatDesLieux;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EtatDesLieux */
class EtatDesLieuxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'commentaire_general' => $this->commentaire_general,
            'signe' => $this->estSigne(),
            'signe_le' => $this->signe_le?->format('d/m/Y H:i:s'),
            'etabli_par' => $this->whenLoaded('etablisseur', fn () => $this->etablisseur?->nomComplet()),
            'lignes' => LigneEtatDesLieuxResource::collection($this->whenLoaded('lignes')),
        ];
    }
}
