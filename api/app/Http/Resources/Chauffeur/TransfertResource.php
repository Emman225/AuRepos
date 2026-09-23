<?php

namespace App\Http\Resources\Chauffeur;

use App\Domain\Transferts\Models\Transfert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espace chauffeur › mes transferts. Le chauffeur SAISIT le code de prise en charge,
 * il ne le lit jamais ici (CdC § 11).
 *
 * @mixin Transfert
 */
class TransfertResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'sejour_reference' => $this->whenLoaded('sejour', fn () => $this->sejour->reference),
            'lieu_de_prise_en_charge' => $this->lieu_de_prise_en_charge,
            'commune' => $this->whenLoaded('commune', fn () => $this->commune->nom),
            'date_heure_prevue' => $this->date_heure_prevue->format('d/m/Y H:i'),
            'nombre_passagers' => $this->nombre_passagers,
            'nombre_bagages' => $this->nombre_bagages,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'vehicule' => $this->whenLoaded('vehicule', fn () => $this->vehicule?->immatriculation),
            'montant_verse_au_chauffeur' => $this->montant_verse_au_chauffeur,
        ];
    }
}
