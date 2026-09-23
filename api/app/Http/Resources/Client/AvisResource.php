<?php

namespace App\Http\Resources\Client;

use App\Domain\Sejours\Models\Avis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Mon avis, à moi seul : je vois son statut de modération, jamais les autres avis en attente.
 *
 * @mixin Avis
 */
class AvisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'commentaire' => $this->commentaire,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'motif_refus' => $this->motif_refus,
        ];
    }
}
