<?php

namespace App\Http\Resources\Sejours;

use App\Domain\Sejours\Models\Occupant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Occupant d'un séjour (fiche de police, P2-SEJ-01). Jamais le numéro de pièce en clair :
 * seulement s'il a été fourni, et s'il a été photographié.
 *
 * @mixin Occupant
 */
class OccupantResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'enfant' => $this->enfant,
            'type_piece' => $this->type_piece,
            'piece_fournie' => $this->numero_piece !== null,
            'telephone' => $this->telephone,
            'pieces' => $this->whenLoaded('pieces', fn () => $this->pieces->map(fn ($p) => [
                'id' => $p->id, 'statut' => $p->statut->value, 'nom_original' => $p->nom_original,
            ])),
        ];
    }
}
