<?php

namespace App\Http\Resources\Assistance;

use App\Domain\Assistance\Models\TicketAssistance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TicketAssistance */
class TicketAssistanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sejour' => $this->whenLoaded('sejour', fn () => [
                'reference' => $this->sejour->reference, 'logement' => $this->sejour->logement?->nom,
            ]),
            'client' => $this->whenLoaded('client', fn () => $this->client?->nomComplet()),
            'sujet' => $this->sujet,
            'message' => $this->message,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'reponse' => $this->reponse,
            'traite_le' => $this->traite_le?->format('d/m/Y H:i:s'),
            'created_at' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
