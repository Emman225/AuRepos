<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Transferts\Models\Transfert;
use App\Domain\Transferts\Services\GestionDesTransferts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche back office d'un transfert. Le code de prise en charge n'apparaît JAMAIS ici — seul
 * son émission est signalée (même patron que `code_d_arrivee_emis` pour un séjour).
 *
 * @mixin Transfert
 */
class TransfertResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sejour_reference' => $this->whenLoaded('sejour', fn () => $this->sejour->reference),
            'lieu_de_prise_en_charge' => $this->lieu_de_prise_en_charge,
            'commune' => $this->whenLoaded('commune', fn () => $this->commune->nom),
            'type_vehicule_souhaite' => $this->whenLoaded('typeVehiculeSouhaite', fn () => $this->typeVehiculeSouhaite->nom),
            'date_heure_prevue' => $this->date_heure_prevue->format('d/m/Y H:i'),
            'nombre_passagers' => $this->nombre_passagers,
            'nombre_bagages' => $this->nombre_bagages,
            'montant' => $this->montant,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'chauffeur' => $this->whenLoaded('chauffeur', fn () => $this->chauffeur?->nomAffiche()),
            'vehicule' => $this->whenLoaded('vehicule', fn () => $this->vehicule?->immatriculation),
            'montant_verse_au_chauffeur' => $this->montant_verse_au_chauffeur,
            'notes' => $this->notes,
            // Jamais le code en clair côté back office (CdC § 11) : juste le fait qu'il a été émis.
            'code_prise_en_charge_emis' => app(CodesSecrets::class)->existe($this->resource, GestionDesTransferts::CODE_PRISE_EN_CHARGE),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
