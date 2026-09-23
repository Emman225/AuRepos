<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Maintenance\Models\ContratRecurrent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ContratRecurrent */
class ContratRecurrentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'periodicite' => $this->periodicite->value,
            'periodicite_libelle' => $this->periodicite->libelle(),
            'prochain_rappel' => $this->prochain_rappel->format('Y-m-d'),
            'actif' => $this->actif,
            'notes' => $this->notes,
        ];
    }
}
