<?php

namespace App\Http\Resources\Client\Repas;

use App\Domain\Repas\Models\Restaurateur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Restaurateur actif, vu du client : sa carte des produits disponibles. @mixin Restaurateur */
class RestaurateurResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nomAffiche(),
            'carte' => ProduitResource::collection($this->whenLoaded('produits')),
        ];
    }
}
