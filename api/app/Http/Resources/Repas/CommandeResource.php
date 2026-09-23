<?php

namespace App\Http\Resources\Repas;

use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Repas\Models\Commande;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue INTERNE d'une commande (back office, restaurateur, livreur) : ne montre JAMAIS le
 * code de livraison en clair, seulement `code_livraison_emis` — même patron que
 * `code_d_arrivee_emis` sur App\Http\Resources\Backoffice\SejourResource. Le code en clair
 * n'apparaît que côté client (App\Http\Resources\Client\Repas\CommandeResource) et n'est
 * jamais LU par le livreur (il le SAISIT).
 *
 * @mixin Commande
 */
class CommandeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sejour_id' => $this->sejour_id,
            'sejour_reference' => $this->sejour?->reference,
            'restaurateur_id' => $this->restaurateur_id,
            'restaurateur' => $this->restaurateur?->nomAffiche(),
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'mode_reglement' => $this->mode_reglement,
            'montant_total' => $this->montant_total,
            'livreur_id' => $this->livreur_id,
            'livreur' => $this->livreur?->nomAffiche(),
            'remuneration_livreur' => $this->remuneration_livreur,
            'code_livraison_emis' => app(CodesSecrets::class)->existe($this->resource, 'livraison'),
            'notes' => $this->notes,
            'lignes' => LigneDeCommandeResource::collection($this->whenLoaded('lignes')),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
