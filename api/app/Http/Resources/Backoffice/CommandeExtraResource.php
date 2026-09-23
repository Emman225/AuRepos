<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Extras\Models\CommandeExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommandeExtra */
class CommandeExtraResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sejour_id' => $this->sejour_id,
            'sejour_reference' => $this->whenLoaded('sejour', fn () => $this->sejour->reference),
            'extra_id' => $this->extra_id,
            'nom_extra' => $this->nom_extra,
            'quantite' => $this->quantite,
            'prix_unitaire' => $this->prix_unitaire,
            'montant_total' => $this->montant_total,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'affecte_a' => $this->whenLoaded('affecteA', fn () => $this->affecteA?->nomComplet()),
            'affecte_le' => $this->affecte_le?->format('d/m/Y H:i:s'),
            'fournie_le' => $this->fournie_le?->format('d/m/Y H:i:s'),
            'demande_par' => $this->whenLoaded('demandePar', fn () => $this->demandePar?->nomComplet()),
            'notes' => $this->notes,
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
