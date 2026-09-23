<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDeLaDemandeAnnulation;
use App\Domain\Sejours\Models\DemandeAnnulation;
use App\Domain\Sejours\Services\DemandesDAnnulation;
use App\Http\Controllers\Controller;
use App\Http\Resources\Sejours\DemandeAnnulationResource;
use App\Support\Api\ReponseApi;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Demandes d'annulation (P2-SEJ-06, CdC § 6.1) : instruction réservée aux administrateurs —
 * le remboursement éventuel passe par un décaissement (`Caisse`), lui-même réservé aux
 * administrateurs dans tout ce projet (même restriction que les autres décaissements).
 */
final class DemandesAnnulationController extends Controller
{
    private const RELATIONS = ['sejour.logement.residence', 'client'];

    public function __construct(private readonly DemandesDAnnulation $demandes) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate(['etat' => ['nullable', Rule::enum(EtatDeLaDemandeAnnulation::class)]]);

        $page = DemandeAnnulation::query()->with(self::RELATIONS)
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $v) => $q->where('etat', $v))
            ->orderByDesc('id')->paginate(25);

        return ReponseApi::page($page, DemandeAnnulationResource::class);
    }

    public function accepter(Request $request, DemandeAnnulation $demande): JsonResponse
    {
        $saisie = $request->validate([
            'mode_de_remboursement' => ['nullable', Rule::enum(ModeDeReglement::class)],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['mode_de_remboursement' => 'mode de règlement du remboursement']);

        $demande = $this->demandes->accepter(
            $demande, $this->administrateur($request),
            isset($saisie['mode_de_remboursement']) ? ModeDeReglement::from($saisie['mode_de_remboursement']) : null,
            $saisie['motif'] ?? null,
        );

        return ReponseApi::succes(new DemandeAnnulationResource($demande->load(self::RELATIONS)), 'Demande acceptée : séjour annulé.');
    }

    public function rejeter(Request $request, DemandeAnnulation $demande): JsonResponse
    {
        $saisie = $request->validate(['motif' => ['required', 'string', 'min:5', 'max:255']], [], ['motif' => 'motif']);

        $demande = $this->demandes->rejeter($demande, $this->administrateur($request), (string) $saisie['motif']);

        return ReponseApi::succes(new DemandeAnnulationResource($demande->load(self::RELATIONS)), 'Demande rejetée : le séjour continue.');
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
