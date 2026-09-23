<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Maintenance\Models\TicketMaintenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un ticket de maintenance OUVERT signalé sur la grille Planning (état « en maintenance »,
 * P2-MNT-01) : juste de quoi le situer, le doser (urgence) et savoir jusqu'à quand le logement
 * est annoncé indisponible. `blocage_id` est renseigné pour un ticket BLOQUANT : ce même
 * blocage figure alors dans `blocages` — l'écran ne doit pas le peindre deux fois.
 * Pour la fiche complète (technicien, coûts), voir TicketMaintenanceResource.
 *
 * @mixin TicketMaintenance
 */
class PlanningTicketMaintenanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement_id' => $this->logement_id,
            'urgence' => $this->urgence->value,
            'urgence_libelle' => $this->urgence->libelle(),
            'statut' => $this->statut->value,
            'bloquant' => $this->blocage_calendrier_id !== null,
            'blocage_id' => $this->blocage_calendrier_id,
            'indisponible_jusquau' => $this->indisponible_jusquau?->format('Y-m-d'),
        ];
    }
}
