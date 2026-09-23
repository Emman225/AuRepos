<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une colonne de la grille Planning (écran back-office React § « Planning ») : juste de quoi
 * étiqueter un logement et sa résidence. Aucun prix, aucune donnée sensible — voir LogementResource
 * pour la fiche complète du catalogue.
 *
 * @mixin Logement
 */
class PlanningLogementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'nom' => $this->nom,
            'residence' => [
                'id' => $this->residence->id,
                'nom' => $this->residence->nom,
            ],
        ];
    }
}
