<?php

namespace App\Http\Resources\Proprietaire;

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Residence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue PROPRIÉTAIRE d'une résidence (lecture seule, CdC § 7) : de quoi la reconnaître et
 * savoir si elle est en vente — jamais les consignes d'accès ni les détails d'exploitation
 * interne, réservés au back office.
 *
 * @mixin Residence
 */
class ResidenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $commune = $this->quartier->commune;

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'lieu' => [
                'commune' => $commune->nom,
                'quartier' => $this->quartier->nom,
                'libelle' => $commune->nom.' › '.$this->quartier->nom,
            ],
            'nombre_logements' => $this->whenCounted('logements'),
            'disponibilite' => $this->disponibilite->value,
            'disponibilite_libelle' => $this->disponibilite === Disponibilite::Disponible ? 'Disponible' : 'Occupée',
            'active' => $this->active,
        ];
    }
}
