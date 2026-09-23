<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Contenu\Models\Diapositive;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Diapositive */
class DiapositiveResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_url' => $this->image_url,
            'legende' => $this->legende,
            'lien' => $this->lien,
            'ordre' => $this->ordre,
            'actif' => $this->actif,
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
