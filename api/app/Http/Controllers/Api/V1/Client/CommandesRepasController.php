<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Models\Restaurateur;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Requests\Repas\CommandeRequest;
use App\Http\Resources\Client\Repas\CommandeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mon espace › Mes commandes de repas (CdC — « Commander repas et boissons pendant le
 * séjour, suivre la préparation et la livraison, code de livraison à remettre au livreur »).
 * Un client ne voit et ne touche que les commandes de SES séjours.
 */
final class CommandesRepasController extends Controller
{
    private const RELATIONS = ['restaurateur', 'lignes'];

    public function index(Request $request, string $reference): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        $commandes = Commande::query()->where('sejour_id', $sejour->id)->with(self::RELATIONS)->orderByDesc('id')->get();

        return ReponseApi::succes(CommandeResource::collection($commandes));
    }

    public function afficher(Request $request, string $reference, Commande $commande): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);
        if ($commande->sejour_id !== $sejour->id) {
            abort(404);
        }

        return ReponseApi::succes(new CommandeResource($commande->load(self::RELATIONS)));
    }

    public function commander(CommandeRequest $request, string $reference, GestionDesCommandes $gestion): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);
        $restaurateur = Restaurateur::query()->where('actif', true)->findOrFail($request->validated('restaurateur_id'));

        /** @var User $client */
        $client = $request->user();
        /** @var list<array{produit_id: int, quantite: int}> $lignes */
        $lignes = $request->validated('lignes');

        $commande = $gestion->commander($sejour, $restaurateur, $client, $lignes, (string) $request->validated('mode_reglement'), $request->validated('notes'));

        return ReponseApi::cree(new CommandeResource($commande->load(self::RELATIONS)), 'Commande envoyée au restaurateur.');
    }

    /** Le séjour d'un autre client N'EXISTE PAS pour moi : 404, jamais 403. */
    private function leMien(Request $request, string $reference): Sejour
    {
        return Sejour::query()
            ->where('reference', $reference)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->firstOrFail();
    }
}
