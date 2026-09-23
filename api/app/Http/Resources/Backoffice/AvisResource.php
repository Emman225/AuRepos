<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Sejours\Models\Avis;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * File de modération des avis (CdC § 5.1, P2-AVI-01) : tout ce qu'un administrateur doit voir
 * pour décider — l'auteur, le logement concerné, la note et le commentaire.
 *
 * @mixin Avis
 */
class AvisResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $sejour = $this->sejour;

        return [
            'id' => $this->id,
            'note' => $this->note,
            'commentaire' => $this->commentaire,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'sejour' => ['reference' => $sejour->reference],
            'client' => $sejour->client ? $sejour->client->nomComplet() : '',
            'logement' => $sejour->logement->nom,
            'residence' => $sejour->logement->residence->nom,
            'motif_refus' => $this->motif_refus,
            'cree_le' => $this->created_at?->format('d/m/Y H:i'),
        ];
    }
}
