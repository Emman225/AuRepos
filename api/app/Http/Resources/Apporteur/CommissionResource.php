<?php

namespace App\Http\Resources\Apporteur;

use App\Domain\Partenaires\Models\CommissionApporteur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommissionApporteur */
class CommissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sejour_reference' => $this->whenLoaded('sejour', fn () => $this->sejour->reference),
            'montant' => $this->montant,
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
