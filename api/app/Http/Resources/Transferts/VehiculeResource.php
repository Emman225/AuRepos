<?php

namespace App\Http\Resources\Transferts;

use App\Domain\Transferts\Models\Vehicule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Un véhicule, rien de sensible : partagé entre le back office et l'espace chauffeur. @mixin Vehicule */
class VehiculeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chauffeur_id' => $this->chauffeur_id,
            'type_vehicule_id' => $this->type_vehicule_id,
            'type_vehicule' => $this->whenLoaded('type', fn () => $this->type->nom),
            'immatriculation' => $this->immatriculation,
            'actif' => $this->actif,
        ];
    }
}
