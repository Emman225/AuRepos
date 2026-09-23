<?php

namespace App\Http\Resources\Proprietaire;

use App\Domain\Catalogue\Models\Logement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue PROPRIÉTAIRE d'un logement (lecture seule, CdC § 7) : jamais le prix propriétaire
 * ni le prix de vente — ces montants suivent leurs propres circuits de négociation et
 * restent une vue back office (CdC § 7.1).
 *
 * @mixin Logement
 */
class LogementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'nom' => $this->nom,
            'resume' => $this->resume(),
            'capacite_de_base' => $this->capacite_de_base,
            'capacite_maximale' => $this->capacite_maximale,
            'etat_publication' => $this->etat_publication->value,
            'etat_publication_libelle' => $this->etat_publication->libelle(),
        ];
    }
}
