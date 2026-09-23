<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Models\Livreur;
use App\Domain\Repas\Services\GestionDesCommandes;
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
}
