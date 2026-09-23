<?php

namespace App\Http\Resources\Publique;

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
            'image' => $this->image_url,
            'legende' => $this->legende,
            'lien' => $this->lien,
        ];
    }
}
