<?php

namespace App\Http\Controllers\Api\V1\Livreur;

use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Commande;
use App\Domain\Repas\Services\GestionDesCommandes;
use App\Http\Controllers\Api\V1\Livreur\Concerns\ResoutLeLivreurConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Repas\CommandeResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace livreur › SES courses de repas : récupère la commande chez le restaurateur, SAISIT
 * le code de livraison du client (CdC — espace livreur). Le code n'est jamais lu à l'avance :
 * ni ce contrôleur ni App\Http\Resources\Repas\CommandeResource ne l'exposent jamais.
 */
final class CoursesController extends Controller
{
    use ResoutLeLivreurConnecte;

    public function index(Request $request): JsonResponse
    {
        $livreur = $this->monLivreur($request);

        return ReponseApi::succes(CommandeResource::collection(
            Commande::query()->where('livreur_id', $livreur->id)
                ->with(['sejour', 'restaurateur', 'lignes'])->orderByDesc('id')->get(),
        ));
    }

    public function afficher(Request $request, Commande $commande): JsonResponse
    {
        $this->verifierAffectation($request, $commande);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'lignes'])));
    }

    /** Le livreur SAISIT le code que le client lui remet en main propre. */
    public function cloturer(Request $request, Commande $commande, GestionDesCommandes $service): JsonResponse
    {
        $this->verifierAffectation($request, $commande);

        $saisie = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'code de livraison']);

        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $commande = $service->cloturerParCode($commande, (string) $saisie['code'], $utilisateur);

        return ReponseApi::succes(new CommandeResource($commande->load(['sejour', 'restaurateur', 'lignes'])), 'Commande livrée.');
    }

    private function verifierAffectation(Request $request, Commande $commande): void
    {
        // La course d'un autre livreur N'EXISTE PAS pour moi : 404, jamais 403.
        if ($commande->livreur_id !== $this->monLivreur($request)->id) {
            abort(404);
        }
    }
}
