<?php

namespace App\Http\Resources\Apporteur;

use App\Domain\Comptes\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class FilleulResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_complet' => $this->nomComplet(),
            'inscrit_le' => $this->created_at?->format('d/m/Y'),
        ];
    }
}
