<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Contenu\Models\AbonneNewsletter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AbonneNewsletter */
class AbonneNewsletterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'nom' => $this->nom,
            'actif' => $this->actif,
            'abonne_le' => $this->abonne_le->format('d/m/Y H:i:s'),
            'desabonne_le' => $this->desabonne_le?->format('d/m/Y H:i:s'),
        ];
    }
}
