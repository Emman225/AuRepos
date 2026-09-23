<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Comptes\Models\Agence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Agence */
class AgenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'adresse' => $this->adresse,
            'telephone' => $this->telephone,
            'active' => $this->active,
            'nombre_utilisateurs' => $this->whenCounted('utilisateurs'),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
