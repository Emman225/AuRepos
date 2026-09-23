<?php

namespace App\Http\Resources\Repas;

use App\Domain\Repas\Models\BaremeLivraisonRepas;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BaremeLivraisonRepas */
class BaremeLivraisonRepasResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'residence_id' => $this->residence_id,
            'residence' => $this->residence?->nom,
            'forfait' => $this->forfait,
        ];
    }
}
