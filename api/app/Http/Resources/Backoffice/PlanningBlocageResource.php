<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Sejours\Models\BlocageCalendrier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un blocage calendrier posé sur la grille Planning (états « en maintenance » et « bloqué
 * propriétaire », entre autres motifs) : juste de quoi le situer et le colorer selon son motif.
 *
 * @mixin BlocageCalendrier
 */
class PlanningBlocageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement_id' => $this->logement_id,
            'motif' => $this->motif,
            'motif_libelle' => BlocageCalendrier::MOTIFS[$this->motif] ?? $this->motif,
            'debut' => $this->debut->format('Y-m-d'),
            'fin' => $this->fin->format('Y-m-d'),
        ];
    }
}
