<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Catalogue\Models\PhotoLogement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PhotoLogement */
class PhotoLogementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'legende' => $this->legende,
            'ordre' => $this->ordre,
            'couverture' => $this->couverture,
            'url' => $this->urlAffichage(),
            'url_vignette' => $this->urlVignette(),
            // Dimensions de l'ORIGINAL : repère des coordonnées de recadrage.
            'largeur' => $this->largeur,
            'hauteur' => $this->hauteur,
            'ajoutee_par_administration' => $this->ajoutee_par_administration,
            'etat' => $this->etat,
        ];
    }
}
