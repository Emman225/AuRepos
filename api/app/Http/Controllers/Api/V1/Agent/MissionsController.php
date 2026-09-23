<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Enums\StatutDeMission;
use App\Domain\Exploitation\Models\Mission;
use App\Domain\Exploitation\Services\Missions;
use App\Domain\Maintenance\Enums\UrgenceTicket;
use App\Http\Controllers\Controller;
use App\Http\Resources\Exploitation\MissionResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

/**
 * Espace agent de terrain › Mes missions de ménage (P2-MEN-01, CdC § 6.4) : celles qui me
 * sont affectées, avec le seul déroulement qui lui revient — démarrer, terminer avec sa
 * clôture détaillée (P2-MEN-03), ajouter des photos.
 */
final class MissionsController extends Controller
{
    private const RELATIONS = ['logement.residence', 'sejour'];

    public function __construct(private readonly Missions $missions) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate(['statut' => ['nullable', Rule::enum(StatutDeMission::class)]]);

        $missions = Mission::query()->with(self::RELATIONS)
            ->where('agent_id', $this->moi($request)->id)
            ->when($filtres['statut'] ?? null, fn (Builder $q, string $v) => $q->where('statut', $v))
            ->orderBy('echeance')->get();

        return ReponseApi::succes(MissionResource::collection($missions));
    }

    public function demarrer(Request $request, Mission $mission): JsonResponse
    {
        $mission = $this->missions->demarrer($mission, $this->moi($request));

        return ReponseApi::succes(new MissionResource($mission->load(self::RELATIONS)), 'Mission démarrée.');
    }

    /** Clôture (P2-MEN-01, P2-MEN-03) : notes, check-list par pièce, linge, produits, anomalie → ticket de maintenance. */
    public function terminer(Request $request, Mission $mission): JsonResponse
    {
        $saisie = $request->validate([
            'notes' => ['nullable', 'string', 'max:1000'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.piece' => ['required_with:checklist', 'string', 'max:100'],
            'checklist.*.propre' => ['nullable', 'boolean'],
            'checklist.*.observation' => ['nullable', 'string', 'max:500'],
            'linge_notes' => ['nullable', 'string', 'max:1000'],
            'produits_notes' => ['nullable', 'string', 'max:1000'],
            'anomalie' => ['nullable', 'boolean'],
            'anomalie_description' => ['nullable', 'string', 'max:1000', 'required_if:anomalie,true'],
            'urgence' => ['nullable', Rule::enum(UrgenceTicket::class)],
            'indisponible_jusquau' => ['nullable', 'date', 'after_or_equal:today', 'required_if:urgence,bloquante'],
        ], [], [
            'notes' => 'notes', 'checklist' => 'check-list', 'linge_notes' => 'linge', 'produits_notes' => 'produits',
            'anomalie' => 'anomalie', 'anomalie_description' => 'description de l’anomalie', 'urgence' => 'urgence',
            'indisponible_jusquau' => 'indisponible jusqu’au',
        ]);

        $mission = $this->missions->terminer($mission, $this->moi($request), $saisie['notes'] ?? null, [
            'checklist' => $saisie['checklist'] ?? null,
            'linge_notes' => $saisie['linge_notes'] ?? null,
            'produits_notes' => $saisie['produits_notes'] ?? null,
            'anomalie' => $saisie['anomalie'] ?? false,
            'anomalie_description' => $saisie['anomalie_description'] ?? null,
            'urgence' => isset($saisie['urgence']) ? UrgenceTicket::from($saisie['urgence']) : UrgenceTicket::Normale,
            'indisponible_jusquau' => isset($saisie['indisponible_jusquau']) ? Carbon::parse($saisie['indisponible_jusquau']) : null,
        ]);

        return ReponseApi::succes(new MissionResource($mission->load(self::RELATIONS)), 'Mission terminée.');
    }

    /** Photo de clôture (P2-MEN-03) : même mécanisme chiffré qu'un état des lieux. */
    public function ajouterUnePhoto(Request $request, Mission $mission): JsonResponse
    {
        $saisie = $request->validate([
            'fichier' => ['required', File::types(['jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ], [], ['fichier' => 'photo']);

        $this->missions->ajouterUnePhoto($mission, $saisie['fichier'], $this->moi($request));

        return ReponseApi::succes(new MissionResource($mission->load(array_merge(self::RELATIONS, ['photos']))), 'Photo ajoutée.');
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
