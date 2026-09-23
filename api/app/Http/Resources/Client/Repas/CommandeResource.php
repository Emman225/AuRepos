<?php

namespace App\Http\Resources\Client\Repas;

use App\Domain\Codes\Services\CodesSecrets;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Http\Resources\Repas\LigneDeCommandeResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue CLIENT d'une commande : « Mes commandes de repas… code de livraison à remettre au
 * livreur » (CdC). Le code en clair n'apparaît que lorsque la commande est EN LIVRAISON —
 * exactement le patron de App\Http\Resources\Client\SejourResource pour le code d'arrivée.
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
            'restaurateur' => $this->restaurateur?->nomAffiche(),
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'mode_reglement' => $this->mode_reglement,
            'montant_total' => $this->montant_total,
            'code_livraison' => $this->etat === EtatDeCommande::EnLivraison
                ? app(CodesSecrets::class)->lirePourLeClient($this->resource, 'livraison')
                : null,
            'lignes' => LigneDeCommandeResource::collection($this->whenLoaded('lignes')),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
