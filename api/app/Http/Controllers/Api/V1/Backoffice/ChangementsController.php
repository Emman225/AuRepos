<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Assistance\Models\Reclamation;
use App\Domain\Assistance\Services\Reclamations;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PourcentageEntreprise;
use App\Domain\Catalogue\Services\PrixDeLogement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\ReductionSurSejour;
use App\Domain\Validation\Models\ChangementAValider;
use App\Domain\Validation\Services\DoubleValidation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\ChangementAValiderResource;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** File des changements en attente d'un second administrateur, et décision. Administrateurs seulement. */
final class ChangementsController extends Controller
{
    public function __construct(
        private readonly DoubleValidation $doubleValidation,
        private readonly PrixDeLogement $prix,
        private readonly PourcentageEntreprise $pourcentageEntreprise,
        private readonly ReductionSurSejour $reductionSurSejour,
        private readonly Reclamations $reclamations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'statut' => ['nullable', Rule::in(['en_attente', 'valide', 'refuse', 'annule'])],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $requete = ChangementAValider::query()->with(['auteur', 'decideur', 'sujet'])
            ->when($filtres['statut'] ?? 'en_attente', fn (Builder $q, string $v) => $q->where('statut', $v));

        $page = FiltrePeriode::depuis($request)->appliquer($requete)->orderByDesc('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, ChangementAValiderResource::class);
    }

    public function decider(Request $request, ChangementAValider $changement): JsonResponse
    {
        $saisie = $request->validate([
            'decision' => ['required', Rule::in(['valider', 'refuser', 'annuler'])],
            'motif' => ['nullable', 'string', 'min:5', 'max:255', 'required_if:decision,refuser'],
            // Avoir / geste commercial sur une réclamation (P2-AST-01) : le trésorier choisit
            // le mode de règlement du décaissement, comme pour un remboursement d'annulation.
            'mode_de_remboursement' => ['nullable', Rule::enum(ModeDeReglement::class)],
        ], ['motif.required_if' => 'Un refus doit être motivé.'], ['decision' => 'décision', 'motif' => 'motif']);

        /** @var User $utilisateur */
        $utilisateur = $request->user();

        match ($saisie['decision']) {
            'valider' => $this->valider($changement, $utilisateur, isset($saisie['mode_de_remboursement']) ? ModeDeReglement::from($saisie['mode_de_remboursement']) : null),
            'refuser' => $this->doubleValidation->refuser($changement, $utilisateur, (string) $saisie['motif']),
            default => $this->doubleValidation->annuler($changement, $utilisateur),
        };

        return ReponseApi::succes(
            new ChangementAValiderResource($changement->refresh()->load(['auteur', 'decideur', 'sujet'])),
            'Décision enregistrée.',
        );
    }

    private function valider(ChangementAValider $changement, User $utilisateur, ?ModeDeReglement $modeDeRemboursement = null): void
    {
        // Une réduction sur séjour a sa propre règle de validateur (LE trésorier désigné,
        // pas « un autre administrateur ») et son propre recalcul : circuit à part entière.
        if ($changement->sujet instanceof Sejour && $changement->champ === 'reduction_pourcentage') {
            $this->reductionSurSejour->confirmer($changement, $utilisateur);

            return;
        }

        // Avoir / geste commercial sur une réclamation (P2-AST-01) : MÊME règle de
        // validateur que la réduction (LE trésorier désigné), payé par décaissement.
        if ($changement->sujet instanceof Reclamation && $changement->champ === Reclamations::CHAMP_AVOIR) {
            $this->reclamations->confirmerAvoir($changement, $utilisateur, $modeDeRemboursement);

            return;
        }

        $this->doubleValidation->valider($changement, $utilisateur, $this->controlePour($changement));
        // Le taux global des Paramètres est mis en cache : une validation doit l'en faire sortir.
        $this->pourcentageEntreprise->apresValidation($changement);
    }

    /** La règle métier propre à la valeur concernée, revérifiée au moment d'appliquer. */
    private function controlePour(ChangementAValider $changement): ?Closure
    {
        $sujet = $changement->sujet;

        return $sujet instanceof Logement && $changement->champ === PrixDeLogement::CHAMP_PRIX_DE_VENTE
            ? $this->prix->controleDuPrixDeVente($sujet)
            : null;
    }
}
