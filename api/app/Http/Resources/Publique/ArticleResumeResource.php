<?php

namespace App\Http\Resources\Publique;

use App\Domain\Contenu\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vignette de la liste du blog, sans le corps de l'article (voir `ArticleResource` pour la fiche).
 *
 * @mixin Article
 */
class ArticleResumeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'titre' => $this->titre,
            'slug' => $this->slug,
            'resume' => $this->resume,
            'image' => $this->image_url,
            'publie_le' => $this->publie_le?->format('d/m/Y'),
        ];
    }
}
