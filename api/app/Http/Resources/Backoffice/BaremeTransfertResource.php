<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Transferts\Models\BaremeTransfert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BaremeTransfert */
class BaremeTransfertResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commune_id' => $this->commune_id,
            'commune' => $this->whenLoaded('commune', fn () => $this->commune->nom),
            'type_vehicule_id' => $this->type_vehicule_id,
            'type_vehicule' => $this->whenLoaded('typeVehicule', fn () => $this->typeVehicule->nom),
            'prix' => $this->prix,
        ];
    }
}
