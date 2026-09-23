<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use App\Domain\Maintenance\Models\ArticleInventaire;
use App\Domain\Maintenance\Services\Inventaire;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ArticleInventaireResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Inventaire d'un logement (P2-MNT-02) : nom, quantité, valeur de remplacement. */
final class InventaireController extends Controller
{
    public function __construct(private readonly Inventaire $inventaire) {}

    public function index(Residence $residence, Logement $logement): JsonResponse
    {
        $articles = ArticleInventaire::query()->where('logement_id', $logement->id)->orderBy('nom')->get();

        return ReponseApi::succes(ArticleInventaireResource::collection($articles));
    }

    public function creer(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'nom' => ['required', 'string', 'max:150'],
            'quantite' => ['required', 'integer', 'min:0', 'max:100000'],
            'valeur_remplacement' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [], ['nom' => 'nom', 'quantite' => 'quantité', 'valeur_remplacement' => 'valeur de remplacement']);

        /** @var User $auteur */
        $auteur = $request->user();
        $article = $this->inventaire->ajouter($logement, $saisie['nom'], (int) $saisie['quantite'], $saisie['valeur_remplacement'] ?? null, $auteur);

        return ReponseApi::cree(new ArticleInventaireResource($article), 'Article ajouté à l’inventaire.');
    }

    public function modifier(Request $request, Residence $residence, Logement $logement, ArticleInventaire $article): JsonResponse
    {
        $this->exigerAppartenance($logement, $article);
        $saisie = $request->validate([
            'nom' => ['sometimes', 'string', 'max:150'],
            'quantite' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'valeur_remplacement' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000000'],
        ], [], ['nom' => 'nom', 'quantite' => 'quantité', 'valeur_remplacement' => 'valeur de remplacement']);

        $article = $this->inventaire->modifier($article, $saisie);

        return ReponseApi::succes(new ArticleInventaireResource($article), 'Article modifié.');
    }

    public function supprimer(Residence $residence, Logement $logement, ArticleInventaire $article): JsonResponse
    {
        $this->exigerAppartenance($logement, $article);
        $this->inventaire->supprimer($article);

        return ReponseApi::succes(null, 'Article supprimé.');
    }

    private function exigerAppartenance(Logement $logement, ArticleInventaire $article): void
    {
        if ($article->logement_id !== $logement->id) {
            abort(404);
        }
    }
}
