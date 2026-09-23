<?php

namespace App\Http\Resources\Publique;

use App\Domain\Contenu\Models\Temoignage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Temoignage
 */
class TemoignageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_client' => $this->nom_client,
            'message' => $this->message,
            'note' => $this->note,
            'photo' => $this->photo_url,
        ];
    }
}
