<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Models\Livreur;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Repas\CommandeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Commandes de repas › suivi par la gestion quotidienne (CdC — « Commandes à confirmer :
 * vérifier le règlement… transmettre au restaurateur » ; « le gestionnaire ou le système
 * affecte un livreur »). Le code de livraison n'est JAMAIS exposé ici (App\Http\Resources
 * \Repas\CommandeResource ne renvoie que `code_livraison_emis`).
 */
final class CommandesRepasController extends Controller
{
    public function __construct(private readonly GestionDesCommandes $commandes) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'etat' => ['nullable', Rule::enum(EtatDeCommande::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Commande::query()
            ->with(['sejour', 'restaurateur.utilisateur', 'livreur.utilisateur', 'lignes'])
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $etat) => $q->where('etat', $etat))
            ->orderByDesc('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, CommandeResource::class);
    }

    public function confirmer(Commande $commande): JsonResponse
    {
        $commande = $this->commandes->confirmer($commande);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'lignes'])), 'Commande confirmée et transmise au restaurateur.');
    }

    public function affecterUnLivreur(Request $request, Commande $commande): JsonResponse
    {
        $saisie = $request->validate([
            'livreur_id' => ['required', 'integer', 'exists:livreurs,id'],
            'remuneration_livreur' => ['required', 'integer', 'min:0'],
        ], [], ['livreur_id' => 'livreur', 'remuneration_livreur' => 'rémunération du livreur']);

        $livreur = Livreur::query()->findOrFail($saisie['livreur_id']);
        $commande = $this->commandes->affecterUnLivreur($commande, $livreur, (int) $saisie['remuneration_livreur']);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'livreur', 'lignes'])), 'Livreur affecté, code de livraison émis.');
    }

    public function refuser(Request $request, Commande $commande): JsonResponse
    {
        $saisie = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['motif' => 'motif']);

        $commande = $this->commandes->refuser($commande, (string) $saisie['motif']);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'lignes'])), 'Commande refusée.');
    }

    /** Extra CdC § 5.2 « premier repas livré à l'arrivée » (P4-API-08) : geste explicite de la réception, après le check-in. */
    public function offrirPremierRepas(Request $request): JsonResponse
    {
        $saisie = $request->validate([
            'sejour_id' => ['required', 'integer', 'exists:sejours,id'],
            'restaurateur_id' => ['required', 'integer', 'exists:restaurateurs,id'],
            'lignes' => ['required', 'array', 'min:1', 'max:50'],
            'lignes.*.produit_id' => ['required', 'integer', 'exists:produits,id'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1', 'max:100'],
        ], [], [
            'sejour_id' => 'séjour', 'restaurateur_id' => 'restaurateur',
            'lignes' => 'lignes de commande', 'lignes.*.produit_id' => 'produit', 'lignes.*.quantite' => 'quantité',
        ]);

        $sejour = Sejour::query()->findOrFail($saisie['sejour_id']);
        $restaurateur = Restaurateur::query()->where('actif', true)->findOrFail($saisie['restaurateur_id']);

        /** @var list<array{produit_id: int, quantite: int}> $lignes */
        $lignes = $saisie['lignes'];
        $commande = $this->commandes->offrirLePremierRepas($sejour, $restaurateur, $lignes);

        return ReponseApi::cree(new CommandeResource($commande->load(['sejour', 'restaurateur', 'lignes'])), 'Premier repas offert enregistré.');
    }
}
