<?php

namespace App\Http\Resources;

use App\Domain\Partenaires\Models\PieceJustificative;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Services\ComptesATerme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientATermeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'client_id' => $this->id,
            'utilisateur' => $this->whenLoaded('utilisateur', fn () => [
                'id' => $this->utilisateur->id,
                'nom' => $this->utilisateur->nomComplet(),
                'email' => $this->utilisateur->email,
                'telephone' => $this->utilisateur->telephone,
            ]),
            'nature' => $this->nature,
            'nature_libelle' => Client::NATURES[$this->nature] ?? $this->nature,
            'raison_sociale' => $this->raison_sociale,
            'statut' => $this->statut_a_terme->value,
            'statut_libelle' => $this->statut_a_terme->libelle(),
            'plafond_credit' => $this->plafond_credit,
            'encours' => $this->estATerme() ? app(ComptesATerme::class)->encours($this->resource) : null,
            'demande_le' => $this->demande_a_terme_le?->format('d/m/Y H:i:s'),
            'motif_refus' => $this->a_terme_motif_refus,
            'traite_le' => $this->a_terme_traite_le?->format('d/m/Y H:i:s'),
            'pieces' => $this->whenLoaded('pieces', fn () => $this->pieces->map(fn (PieceJustificative $p): array => [
                'id' => $p->id, 'type' => $p->type->value, 'type_libelle' => $p->type->libelle(),
                'statut' => $p->statut->value, 'statut_libelle' => $p->statut->libelle(),
                'nom_original' => $p->nom_original, 'motif_refus' => $p->motif_refus,
                'deposee_le' => $p->created_at?->format('d/m/Y H:i:s'),
            ])),
        ];
    }
}
