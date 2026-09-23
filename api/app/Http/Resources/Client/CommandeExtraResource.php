<?php

namespace App\Http\Resources\Client;

use App\Domain\Extras\Models\CommandeExtra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Mon espace › Mes extras (P2-EXT-01). @mixin CommandeExtra */
class CommandeExtraResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'nom_extra' => $this->nom_extra,
            'quantite' => $this->quantite,
            'prix_unitaire' => $this->prix_unitaire,
            'montant_total' => $this->montant_total,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'notes' => $this->notes,
            'cree_le' => $this->created_at?->format('d/m/Y H:i'),
        ];
    }
}
