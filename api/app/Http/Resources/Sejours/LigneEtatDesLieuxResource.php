<?php

namespace App\Http\Resources\Sejours;

use App\Domain\Sejours\Models\LigneEtatDesLieux;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LigneEtatDesLieux */
class LigneEtatDesLieuxResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'observation' => $this->observation,
            'ordre' => $this->ordre,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($p) => [
                'id' => $p->id, 'nom_original' => $p->nom_original,
            ])),
        ];
    }
}
