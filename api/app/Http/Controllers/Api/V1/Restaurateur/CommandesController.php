<?php

namespace App\Http\Controllers\Api\V1\Restaurateur;

use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Http\Controllers\Api\V1\Restaurateur\Concerns\ResoutLeRestaurateurConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Repas\CommandeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace restaurateur › SES commandes : voir, démarrer la préparation, marquer prête avec
 * le bon de préparation (quantités servies). Jamais le code de livraison (il n'apparaît
 * même pas dans App\Http\Resources\Repas\CommandeResource).
 */
final class CommandesController extends Controller
{
    use ResoutLeRestaurateurConnecte;

    public function index(Request $request): JsonResponse
    {
        $restaurateur = $this->monRestaurateur($request);

        return ReponseApi::succes(CommandeResource::collection(
            Commande::query()->where('restaurateur_id', $restaurateur->id)
                ->with(['sejour', 'restaurateur', 'livreur', 'lignes'])->orderByDesc('id')->get(),
        ));
    }

    public function afficher(Request $request, Commande $commande): JsonResponse
    {
        $this->verifierAppartenance($request, $commande);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'livreur', 'lignes'])));
    }

    public function demarrerPreparation(Request $request, Commande $commande, GestionDesCommandes $service): JsonResponse
    {
        $restaurateur = $this->monRestaurateur($request);
        $commande = $service->demarrerPreparation($commande, $restaurateur);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'lignes'])), 'Préparation démarrée.');
    }

    /** Le bon de préparation : quantité réellement servie par ligne. */
    public function marquerPrete(Request $request, Commande $commande, GestionDesCommandes $service): JsonResponse
    {
        $restaurateur = $this->monRestaurateur($request);
        $saisie = $request->validate([
            'quantites_servies' => ['required', 'array', 'min:1'],
            'quantites_servies.*' => ['required', 'integer', 'min:0'],
        ], [], ['quantites_servies' => 'quantités servies']);

        /** @var array<int, int> $quantites */
        $quantites = [];
        foreach ($saisie['quantites_servies'] as $ligneId => $quantite) {
            $quantites[(int) $ligneId] = (int) $quantite;
        }

        $commande = $service->marquerPrete($commande, $restaurateur, $quantites);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'lignes'])), 'Commande prête : bon de préparation enregistré.');
    }

    private function verifierAppartenance(Request $request, Commande $commande): void
    {
        if ($commande->restaurateur_id !== $this->monRestaurateur($request)->id) {
            abort(404);
        }
    }
}
