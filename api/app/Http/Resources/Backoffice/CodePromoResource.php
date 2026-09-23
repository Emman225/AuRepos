<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Tarification\Models\CodePromo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CodePromo */
class CodePromoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'valeur' => $this->valeur,
            'date_debut' => $this->date_debut->format('d/m/Y'),
            'date_fin' => $this->date_fin->format('d/m/Y'),
            'residence' => $this->residence ? ['id' => $this->residence->id, 'nom' => $this->residence->nom] : null,
            'actif' => $this->actif,
            'description' => $this->description,
            'valable_aujourd_hui' => $this->valableLe(now()),
        ];
    }
}
