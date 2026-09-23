<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Contenu\Models\Banniere;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Banniere */
class BanniereResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'sous_titre' => $this->sous_titre,
            'image_url' => $this->image_url,
            'lien' => $this->lien,
            'ordre' => $this->ordre,
            'actif' => $this->actif,
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
