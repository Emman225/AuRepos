<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Maintenance\Models\TicketMaintenance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketMaintenance */
class TicketMaintenanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement' => $this->whenLoaded('logement', fn () => ['id' => $this->logement->id, 'nom' => $this->logement->nom]),
            'mission_id' => $this->mission_id,
            'origine' => $this->origine->value,
            'origine_libelle' => $this->origine->libelle(),
            'urgence' => $this->urgence->value,
            'urgence_libelle' => $this->urgence->libelle(),
            'description' => $this->description,
            'technicien_nom' => $this->technicien_nom,
            'technicien_contact' => $this->technicien_contact,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'cout_montant' => $this->cout_montant,
            'cout_impute_a' => $this->cout_impute_a?->value,
            'cout_impute_a_libelle' => $this->cout_impute_a?->libelle(),
            'indisponible_jusquau' => $this->indisponible_jusquau?->format('Y-m-d'),
            'resolue_le' => $this->resolue_le?->format('d/m/Y H:i:s'),
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
