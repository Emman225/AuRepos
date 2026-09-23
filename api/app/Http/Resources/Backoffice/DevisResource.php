<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Sejours\Models\Devis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue BACK OFFICE (Direction) des devis établis, en attente de transformation (CdC § 9.1).
 *
 * @mixin Devis
 */
class DevisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $residence = $this->logement->residence;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'etat' => $this->etat,
            'client' => ['id' => $this->client->id, 'nom' => $this->client->nomComplet(), 'email' => $this->client->email, 'telephone' => $this->client->telephone],
            'logement' => ['id' => $this->logement->id, 'reference' => $this->logement->reference, 'nom' => $this->logement->nom, 'residence' => $residence->nom, 'residence_id' => $residence->id],
            'arrivee' => $this->arrivee->format('Y-m-d'),
            'depart' => $this->depart->format('Y-m-d'),
            'adultes' => $this->adultes,
            'enfants' => $this->enfants,
            'net_a_payer' => $this->net_a_payer,
            'sejour' => $this->whenLoaded('sejour', fn () => $this->sejour?->reference),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
