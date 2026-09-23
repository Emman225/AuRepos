<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Catalogue\Models\Residence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue BACK OFFICE d'une résidence : adresse exacte et consignes d'accès comprises.
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
            'slug' => $this->slug,
            'proprietaire' => [
                'id' => $this->proprietaire->id,
                'nom' => $this->proprietaire->nomAffiche(),
                'interne' => $this->proprietaire->interne,
            ],
            'lieu' => [
                'quartier_id' => $this->quartier_id,
                'quartier' => $this->quartier->nom,
                'commune_id' => $commune->id,
                'commune' => $commune->nom,
                'libelle' => $commune->nom.' › '.$this->quartier->nom,
            ],
            'adresse' => $this->adresse,
            'repere' => $this->repere,
            'latitude' => $this->getAttribute('latitude'),
            'longitude' => $this->getAttribute('longitude'),
            'description' => $this->description,
            'consignes_acces' => $this->consignes_acces,
            'mode_vente' => $this->mode_vente->value,
            'disponibilite' => $this->disponibilite->value,
            'reouverture_prevue_le' => $this->reouverture_prevue_le?->format('d/m/Y'),
            'active' => $this->active,
            'mise_en_avant' => $this->mise_en_avant,
            'nombre_logements' => $this->whenCounted('logements'),
            'equipements' => $this->whenLoaded('equipements', fn () => $this->equipements->map->only(['id', 'nom', 'icone'])),
            'logements' => LogementResource::collection($this->whenLoaded('logements')),
        ];
    }
}
