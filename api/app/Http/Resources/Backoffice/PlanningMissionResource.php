<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Exploitation\Models\Mission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Une mission de ménage posée sur la grille Planning (état « en ménage », P2-MEN-01) : juste
 * de quoi la situer et la colorer. Pour le détail (agent, notes…), voir MissionResource.
 *
 * @mixin Mission
 */
class PlanningMissionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement_id' => $this->logement_id,
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'echeance' => $this->echeance->format('Y-m-d'),
        ];
    }
}
