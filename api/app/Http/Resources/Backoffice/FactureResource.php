<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Fiscalite\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Facture */
class FactureResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'sejour' => $this->whenLoaded('sejour', fn () => [
                'id' => $this->sejour->id, 'reference' => $this->sejour->reference,
                'arrivee' => $this->sejour->arrivee->format('d/m/Y'), 'depart' => $this->sejour->depart->format('d/m/Y'),
            ]),
            'client' => $this->whenLoaded('client', fn () => ['id' => $this->client->id, 'nom' => $this->client->nomComplet(), 'email' => $this->client->email]),
            'facture_origine' => $this->whenLoaded('factureOrigine', fn () => $this->factureOrigine ? ['id' => $this->factureOrigine->id, 'numero' => $this->factureOrigine->numero] : null),
            'motif_avoir' => $this->motif_avoir,
            'montant_ht' => $this->montant_ht,
            'montant_tva' => $this->montant_tva,
            'autres_taxes' => $this->autres_taxes,
            'montant_ttc' => $this->montant_ttc,
            'lignes' => $this->lignes,
            'statut_transmission' => $this->statut_transmission->value,
            'statut_transmission_libelle' => $this->statut_transmission->libelle(),
            'reference_dgi' => $this->reference_dgi,
            'token_qr' => $this->token_qr,
            'ncc_dgi' => $this->ncc_dgi,
            'solde_stickers' => $this->solde_stickers,
            'motif_refus_dgi' => $this->motif_refus_dgi,
            'transmise_par' => $this->whenLoaded('transmetteur', fn () => $this->transmetteur?->nomComplet()),
            'transmise_le' => $this->transmise_le?->format('d/m/Y H:i:s'),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
