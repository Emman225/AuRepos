<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Partenaires\Models\CommissionApporteur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommissionApporteur */
class CommissionApporteurResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sejour_id' => $this->sejour_id,
            'sejour_reference' => $this->whenLoaded('sejour', fn () => $this->sejour->reference),
            'reglement_id' => $this->reglement_id,
            'montant' => $this->montant,
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
