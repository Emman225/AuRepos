<?php

namespace App\Http\Resources\Publique;

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
            'titre' => $this->titre,
            'slug' => $this->slug,
            'resume' => $this->resume,
            'contenu' => $this->contenu,
            'image' => $this->image_url,
            'publie_le' => $this->publie_le?->format('d/m/Y'),
        ];
    }
}
