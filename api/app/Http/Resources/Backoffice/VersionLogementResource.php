<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Catalogue\Models\VersionLogement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VersionLogement */
class VersionLogementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'logement_id' => $this->logement_id,
            'valeurs' => $this->valeurs,
            'statut' => $this->statut,
            'propose_par' => $this->auteur->nomComplet(),
            'propose_le' => $this->created_at?->format('d/m/Y H:i:s'),
            'decide_par' => $this->decideur?->nomComplet(),
            'decide_le' => $this->decide_le?->format('d/m/Y H:i:s'),
            'motif_refus' => $this->motif_refus,
        ];
    }
}
