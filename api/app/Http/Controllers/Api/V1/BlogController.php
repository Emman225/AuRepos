<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Contenu\Enums\StatutArticle;
use App\Domain\Contenu\Models\Article;
use App\Http\Controllers\Controller;
use App\Http\Resources\Publique\ArticleResource;
use App\Http\Resources\Publique\ArticleResumeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Blog public (CdC § 12) : seuls les articles publiés existent pour le public. */
final class BlogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate(['par_page' => ['nullable', 'integer', 'min:5', 'max:50']]);

        $page = Article::query()
            ->where('statut', StatutArticle::Publie)
            ->orderByDesc('publie_le')
            ->paginate((int) ($filtres['par_page'] ?? 12));

        return ReponseApi::page($page, ArticleResumeResource::class);
    }

    public function afficher(string $slug): JsonResponse
    {
        $article = Article::query()->where('slug', $slug)->where('statut', StatutArticle::Publie)->firstOrFail();

        return ReponseApi::succes(new ArticleResource($article));
    }
}
