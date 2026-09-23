<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Assistance\Enums\EtatDeLaReclamation;
use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Assistance\Services\Reclamations;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assistance\ReclamationResource;
use App\Http\Resources\Backoffice\ChangementAValiderResource;
use App\Support\Api\ReponseApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Réclamations (P2-BO-03, CdC § 6.1) : instruction réservée aux administrateurs — un avoir
 * / geste commercial est PROPOSÉ ici (double validation générique, `ChangementsController`),
 * jamais confirmé ici : seul le trésorier désigné confirme, comme une réduction sur séjour.
 */
final class ReclamationsController extends Controller
{
    private const RELATIONS = ['sejour.logement.residence', 'commande.restaurateur', 'client'];

    public function __construct(private readonly Reclamations $reclamations) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'statut' => ['nullable', Rule::enum(EtatDeLaReclamation::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Reclamation::query()->with(self::RELATIONS)
            ->when($filtres['statut'] ?? null, fn (Builder $q, string $v) => $q->where('statut', $v))
            ->orderByDesc('id')->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ReclamationResource::class);
    }

    public function fermer(Request $request, Reclamation $reclamation): JsonResponse
    {
        $saisie = $request->validate(['reponse' => ['nullable', 'string', 'min:5', 'max:2000']], [], ['reponse' => 'réponse']);

        $reclamation = $this->reclamations->fermer($reclamation, $this->administrateur($request), $saisie['reponse'] ?? null);

        return ReponseApi::succes(new ReclamationResource($reclamation->load(self::RELATIONS)), 'Réclamation fermée.');
    }

    /** Propose un avoir / geste commercial (CdC § 6.1) : rejoint la file générique des changements à valider. */
    public function proposerUnAvoir(Request $request, Reclamation $reclamation): JsonResponse
    {
        $saisie = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'motif' => ['required', 'string', 'min:5', 'max:255'],
        ], [], ['montant' => 'montant', 'motif' => 'motif']);

        $changement = $this->reclamations->proposerUnAvoir(
            $reclamation, (int) $saisie['montant'], (string) $saisie['motif'], $this->administrateur($request),
        );

        return ReponseApi::cree(new ChangementAValiderResource($changement), 'Avoir proposé : en attente de confirmation du trésorier désigné.');
    }

    private function administrateur(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        if (! $utilisateur->profil->estAdministrateur()) {
            throw new AuthorizationException;
        }

        return $utilisateur;
    }
}
