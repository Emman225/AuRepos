<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Tarification\Models\PrixNegocie;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PrixNegocie */
class PrixNegocieResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client' => ['id' => $this->client->id, 'nom' => $this->client->nomComplet(), 'email' => $this->client->email],
            'type_logement' => ['id' => $this->type->id, 'nom' => $this->type->nom],
            'tarif_par_nuit' => $this->tarif_par_nuit,
            'actif' => $this->actif,
            'notes' => $this->notes,
            'modifie_le' => $this->updated_at?->format('d/m/Y H:i:s'),
        ];
    }
}
