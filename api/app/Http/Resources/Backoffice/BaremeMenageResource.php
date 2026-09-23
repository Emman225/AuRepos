<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Exploitation\Models\BaremeMenage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BaremeMenage */
class BaremeMenageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type_logement' => $this->whenLoaded('typeLogement', fn () => ['id' => $this->typeLogement->id, 'nom' => $this->typeLogement->nom]),
            'forfait' => $this->forfait,
            'plancher' => $this->plancher,
        ];
    }
}
