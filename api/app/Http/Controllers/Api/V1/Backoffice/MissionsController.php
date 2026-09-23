<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Enums\StatutDeMission;
use App\Domain\Exploitation\Models\Mission;
use App\Domain\Exploitation\Services\Missions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Exploitation\MissionResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Missions de ménage (P2-MEN-01, CdC § 6.4), vues côté back office : consultation,
 * affectation à un agent de terrain, filtrage. Le déroulement (démarrer / terminer)
 * reste au self-service de l'agent (routes/api_v1/agent.php).
 *
 * Cloisonné par résidence comme les séjours (CdC § 9.5) : un gestionnaire ne voit que
 * les missions des logements de SES résidences.
 */
final class MissionsController extends Controller
{
    private const RELATIONS = ['logement.residence', 'sejour', 'agent'];

    public function __construct(
        private readonly Missions $missions,
        private readonly PerimetreGestionnaire $perimetre,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'statut' => ['nullable', Rule::enum(StatutDeMission::class)],
            'logement_id' => ['nullable', 'integer'],
            'agent_id' => ['nullable', 'integer'],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $autorisees = $this->perimetre->residencesAutorisees($utilisateur);

        $page = Mission::query()->with(self::RELATIONS)
            ->when($autorisees !== null, fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->whereIn('residence_id', $autorisees ?? [])))
            ->when($filtres['statut'] ?? null, fn (Builder $q, string $v) => $q->where('statut', $v))
            ->when($filtres['logement_id'] ?? null, fn (Builder $q, int $v) => $q->where('logement_id', $v))
            ->when($filtres['agent_id'] ?? null, fn (Builder $q, int $v) => $q->where('agent_id', $v))
            ->orderBy('echeance')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, MissionResource::class);
    }

    public function afficher(Request $request, Mission $mission): JsonResponse
    {
        $this->exigerVisible($mission, $request);

        return ReponseApi::succes(new MissionResource($mission->load(self::RELATIONS)));
    }

    /** Ménage demandé (CdC § 6.4) : ad hoc, motivé, sur un logement — pas forcément lié à un séjour. */
    public function demander(Request $request, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:500'],
            'echeance' => ['required', 'date'],
        ], [], ['motif' => 'motif', 'echeance' => 'échéance']);

        /** @var User $auteur */
        $auteur = $request->user();
        $mission = $this->missions->demander($logement, (string) $saisie['motif'], $auteur, Carbon::parse($saisie['echeance']));

        return ReponseApi::cree(new MissionResource($mission->load(self::RELATIONS)), 'Ménage demandé.');
    }

    public function affecter(Request $request, Mission $mission): JsonResponse
    {
        $this->exigerVisible($mission, $request);
        $saisie = $request->validate(['agent_id' => ['required', 'integer', 'exists:users,id']], [], ['agent_id' => 'agent']);

        /** @var User $auteur */
        $auteur = $request->user();
        $agent = User::query()->findOrFail($saisie['agent_id']);
        $mission = $this->missions->affecter($mission, $agent, $auteur);

        return ReponseApi::succes(new MissionResource($mission->load(self::RELATIONS)), 'Mission affectée.');
    }

    /** Même règle que `CloisonnerResidence` (CdC § 9.5) : un gestionnaire hors résidence reçoit un 404, jamais un 403. */
    private function exigerVisible(Mission $mission, Request $request): void
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        if (! $this->perimetre->residenceVisible($mission->logement->residence_id, $utilisateur)) {
            abort(404);
        }
    }
}
