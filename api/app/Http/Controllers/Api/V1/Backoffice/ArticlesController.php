<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Contenu\Enums\StatutArticle;
use App\Domain\Contenu\Models\Article;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ArticleResource;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Blog (CdC § 12, écran Paramètres › Divers, P1-BO-10). */
final class ArticlesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filtres = $this->validerLesFiltres($request);

        $page = $this->requeteFiltree($filtres)->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ArticleResource::class);
    }

    /** Export Excel / Word / PDF de la liste, mêmes filtres que l'écran (CdC § 6.8). */
    public function exporter(Request $request): Response
    {
        $filtres = $this->validerLesFiltres($request, exiger: true);

        $articles = $this->requeteFiltree($filtres)->orderByDesc('id')->limit(5000)->get();

        $export = new ExportDeListe('Articles', [
            'titre' => 'Titre', 'auteur' => 'Auteur', 'statut' => 'Statut', 'publie_le' => 'Publié le',
        ], $articles->map(fn (Article $a): array => [
            'titre' => $a->titre,
            'auteur' => $a->auteur->nomComplet(),
            'statut' => $a->statut->value,
            'publie_le' => $a->publie_le?->format('d/m/Y') ?? '',
        ])->all());

        return $export->reponse($filtres['format']);
    }

    /** @return array<string, mixed> */
    private function validerLesFiltres(Request $request, bool $exiger = false): array
    {
        return $request->validate([
            'recherche' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', new Enum(StatutArticle::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'format' => [$exiger ? 'required' : 'nullable', Rule::in(['xlsx', 'docx', 'pdf'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return Builder<Article>
     */
    private function requeteFiltree(array $filtres): Builder
    {
        return Article::query()->with('auteur')
            ->when($filtres['recherche'] ?? null, function (Builder $q, string $v): void {
                $q->where('titre', 'ilike', '%'.addcslashes($v, '%_\\').'%');
            })
            ->when($filtres['statut'] ?? null, fn (Builder $q, string $v) => $q->where('statut', $v));
    }

    public function afficher(Article $article): JsonResponse
    {
        return ReponseApi::succes(new ArticleResource($article->load('auteur')));
    }

    public function creer(Request $request): JsonResponse
    {
        $saisie = $this->valider($request);

        /** @var User $auteur */
        $auteur = $request->user();
        $saisie['slug'] = $this->slugUnique($saisie['titre']);
        $saisie['auteur_id'] = $auteur->id;
        if (($saisie['statut'] ?? StatutArticle::Brouillon->value) === StatutArticle::Publie->value) {
            $saisie['publie_le'] = now();
        }

        $article = Article::create($saisie);

        return ReponseApi::cree(new ArticleResource($article->load('auteur')), 'Article créé.');
    }

    public function modifier(Request $request, Article $article): JsonResponse
    {
        $saisie = $this->valider($request, $article);

        if (array_key_exists('statut', $saisie) && $saisie['statut'] === StatutArticle::Publie->value && $article->statut !== StatutArticle::Publie) {
            $saisie['publie_le'] = now();
        }

        $article->update($saisie);

        return ReponseApi::succes(new ArticleResource($article->refresh()->load('auteur')), 'Article modifié.');
    }

    /** @return array<string, mixed> */
    private function valider(Request $request, ?Article $article = null): array
    {
        return $request->validate([
            'titre' => ['sometimes', 'required', 'string', 'max:200'],
            'resume' => ['sometimes', 'nullable', 'string', 'max:500'],
            'contenu' => ['sometimes', 'required', 'string'],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'statut' => ['sometimes', 'required', new Enum(StatutArticle::class)],
        ], [], [
            'titre' => 'titre', 'resume' => 'résumé', 'contenu' => 'contenu',
            'image_url' => 'image', 'statut' => 'statut',
        ]);
    }

    private function slugUnique(string $titre): string
    {
        $base = Str::slug($titre) ?: 'article';
        $slug = $base;
        $n = 2;
        while (Article::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
