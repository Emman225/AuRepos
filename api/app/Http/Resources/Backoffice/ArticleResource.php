<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Contenu\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Article */
class ArticleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'slug' => $this->slug,
            'resume' => $this->resume,
            'contenu' => $this->contenu,
            'image_url' => $this->image_url,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'publie_le' => $this->publie_le?->format('d/m/Y H:i:s'),
            'auteur' => $this->whenLoaded('auteur', fn () => ['id' => $this->auteur->id, 'nom' => $this->auteur->nomComplet()]),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
