<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Partenaires\Models\PieceJustificative;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jamais le chemin du fichier : la pièce ne se lit que par la route de téléchargement.
 *
 * @mixin PieceJustificative
 */
class PieceJustificativeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_libelle' => $this->type->libelle(),
            'nom_original' => $this->nom_original,
            'mime' => $this->mime,
            'taille_octets' => $this->taille_octets,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'motif_refus' => $this->motif_refus,
            'expire_le' => $this->expire_le?->format('d/m/Y'),
            'valable' => $this->estValable(),
            'deposee_le' => $this->created_at?->format('d/m/Y H:i:s'),
            'verifiee_le' => $this->verifiee_le?->format('d/m/Y H:i:s'),
        ];
    }
}
