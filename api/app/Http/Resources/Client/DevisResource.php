<?php

namespace App\Http\Resources\Client;

use App\Domain\Sejours\Models\Devis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce que le CLIENT voit de son devis : les mêmes prix figés qu'une réservation (CdC § 5.1).
 *
 * @mixin Devis
 */
class DevisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $logement = $this->logement;
        $residence = $logement->residence;

        return [
            'reference' => $this->reference,
            'etat' => $this->etat,
            'etat_libelle' => match ($this->etat) {
                'en_attente' => 'En attente de transformation',
                'transforme' => 'Transformé en réservation',
                default => 'Archivé',
            },
            'arrivee' => $this->arrivee->format('Y-m-d'),
            'depart' => $this->depart->format('Y-m-d'),
            'nombre_de_nuits' => (int) $this->arrivee->diffInDays($this->depart),
            'adultes' => $this->adultes,
            'enfants' => $this->enfants,
            'logement' => [
                'reference' => $logement->reference, 'nom' => $logement->nom, 'resume' => $logement->resume(),
                'residence' => $residence->nom,
                'lieu' => ['commune' => $residence->quartier->commune->nom, 'quartier' => $residence->quartier->nom],
            ],
            'code_promo' => $this->code_promo,
            'devis' => $this->devis,
            'net_a_payer' => $this->net_a_payer,
            'caution' => $this->caution,
            'points_utilises' => $this->points_utilises,
            'reduction_points' => $this->reduction_points,
            'sejour' => $this->whenLoaded('sejour', fn () => $this->sejour?->reference),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
