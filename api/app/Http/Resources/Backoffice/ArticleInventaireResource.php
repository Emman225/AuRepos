<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Maintenance\Models\ArticleInventaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ArticleInventaire */
class ArticleInventaireResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'quantite' => $this->quantite,
            'valeur_remplacement' => $this->valeur_remplacement,
        ];
    }
}
