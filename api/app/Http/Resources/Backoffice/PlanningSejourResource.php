<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Sejours\Models\Sejour;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un bloc posé sur la grille Planning : juste de quoi le dessiner et l'étiqueter. Comme
 * SejourResource, elle ne contient JAMAIS le code d'arrivée ni aucune autre donnée sensible
 * (montants, règlement…) — pour ça, la fiche complète du séjour (CdC § 6.3).
 *
 * @mixin Sejour
 */
class PlanningSejourResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'logement_id' => $this->logement_id,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'client_nom' => $this->client?->nomComplet(),
            'arrivee' => $this->arrivee->format('Y-m-d'),
            'depart' => $this->depart->format('Y-m-d'),
        ];
    }
}
