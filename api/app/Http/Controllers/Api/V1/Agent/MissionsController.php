<?php

namespace App\Http\Controllers\Api\V1\Agent;

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
use Illuminate\Validation\Rule;

/**
 * Espace agent de terrain › Mes missions de ménage (P2-MEN-01, CdC § 6.4) : celles qui me
 * sont affectées, avec le seul déroulement qui lui revient — démarrer, terminer.
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

    public function terminer(Request $request, Mission $mission): JsonResponse
    {
        $saisie = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $mission = $this->missions->terminer($mission, $this->moi($request), $saisie['notes'] ?? null);

        return ReponseApi::succes(new MissionResource($mission->load(self::RELATIONS)), 'Mission terminée.');
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
