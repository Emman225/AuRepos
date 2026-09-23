<?php

namespace App\Http\Resources\Repas;

use App\Domain\Repas\Models\LigneDeCommande;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LigneDeCommande */
class LigneDeCommandeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'produit_id' => $this->produit_id,
            'nom_produit' => $this->nom_produit,
            'prix_unitaire_vente' => $this->prix_unitaire_vente,
            'quantite_commandee' => $this->quantite_commandee,
            'quantite_servie' => $this->quantite_servie,
            'montant' => $this->montant(),
        ];
    }
}
