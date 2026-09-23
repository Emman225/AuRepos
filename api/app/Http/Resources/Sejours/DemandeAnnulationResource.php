<?php

namespace App\Http\Resources\Sejours;

use App\Domain\Sejours\Models\DemandeAnnulation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DemandeAnnulation */
class DemandeAnnulationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sejour' => $this->whenLoaded('sejour', fn () => [
                'reference' => $this->sejour->reference, 'arrivee' => $this->sejour->arrivee->format('Y-m-d'),
                'depart' => $this->sejour->depart->format('Y-m-d'), 'net_a_payer' => $this->sejour->net_a_payer,
                'etat' => $this->sejour->etat->value,
            ]),
            'client' => $this->whenLoaded('client', fn () => $this->client?->nomComplet()),
            'motif_client' => $this->motif_client,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'montant_retenu' => $this->montant_retenu,
            'montant_rembourse' => $this->montant_rembourse,
            'motif_decision' => $this->motif_decision,
            'instruite_le' => $this->instruite_le?->format('d/m/Y H:i:s'),
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
