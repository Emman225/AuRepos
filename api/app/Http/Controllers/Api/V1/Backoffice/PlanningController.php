<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\Planning;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PlanningBlocageResource;
use App\Http\Resources\Backoffice\PlanningLogementResource;
use App\Http\Resources\Backoffice\PlanningMissionResource;
use App\Http\Resources\Backoffice\PlanningSejourResource;
use App\Http\Resources\Backoffice\PlanningTicketMaintenanceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Planning (grille logements × jours, façon PMS) : agrège en un seul appel les logements, les
 * séjours qui occupent le calendrier, les missions de ménage et les blocages calendrier sur une
 * période, pour l'écran back-office React (P2-BO-01). Lecture seule — aucune nouvelle table :
 * un agrégat de ce que « Réservations » (SejoursController), « Catalogue » (LogementsController)
 * et « Ménage » (MissionsController) exposent déjà, cloisonné au périmètre du gestionnaire comme
 * eux (CdC § 9.5). Le calcul (missions, blocages, indicateurs P2-PLA-03) vit dans le service
 * Planning ; ce contrôleur ne fait qu'orchestrer et cloisonner.
 */
final class PlanningController extends Controller
{
    public function __construct(
        private readonly PerimetreGestionnaire $perimetre,
        private readonly Planning $planning,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'du' => ['required', 'date_format:Y-m-d'],
            'au' => ['required', 'date_format:Y-m-d', 'after_or_equal:du'],
            'residence_id' => ['nullable', 'integer'],
        ], [], ['du' => 'début de période', 'au' => 'fin de période']);

        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $autorisees = $this->perimetre->residencesAutorisees($utilisateur);

        $logements = Logement::query()
            ->with('residence')
            ->when($autorisees !== null, fn (Builder $q) => $q->whereIn('residence_id', $autorisees ?? []))
            ->when($filtres['residence_id'] ?? null, fn (Builder $q, int $v) => $q->where('residence_id', $v))
            ->orderBy('nom')
            ->get();

        $logementIds = $logements->pluck('id')->all();
        $du = Carbon::parse($filtres['du']);
        $au = Carbon::parse($filtres['au']);

        $sejours = Sejour::query()
            ->with('client')
            ->whereIn('logement_id', $logementIds)
            ->whereIn('etat', $this->etatsQuiOccupentLeCalendrier())
            // Chevauchement de deux intervalles « date de début incluse, date de fin exclue »,
            // comme le fait déjà Calendrier::estLibre() pour la disponibilité : le séjour tient
            // [arrivee, depart[, la période demandée [du, au] devient donc [du, au + 1 jour[.
            ->whereRaw(
                'daterange(arrivee, depart, \'[)\') && daterange(?, ?, \'[)\')',
                [$filtres['du'], $au->copy()->addDay()->toDateString()],
            )
            ->get();

        return ReponseApi::succes([
            'logements' => PlanningLogementResource::collection($logements),
            'sejours' => PlanningSejourResource::collection($sejours),
            'missions' => PlanningMissionResource::collection($this->planning->missions($logementIds, $du, $au)),
            'blocages' => PlanningBlocageResource::collection($this->planning->blocages($logementIds, $du, $au)),
            'tickets_maintenance' => PlanningTicketMaintenanceResource::collection($this->planning->ticketsMaintenance($logementIds, $du, $au)),
            'indicateurs' => $this->planning->indicateurs($sejours, $logements->count(), $du, $au),
        ]);
    }

    /**
     * Un séjour annulé ou en no-show libère ses dates (EtatDuSejour::occupeLeCalendrier) :
     * il ne doit jamais apparaître sur le planning.
     *
     * @return list<string>
     */
    private function etatsQuiOccupentLeCalendrier(): array
    {
        return array_values(array_map(
            fn (EtatDuSejour $etat): string => $etat->value,
            array_filter(EtatDuSejour::cases(), fn (EtatDuSejour $etat): bool => $etat->occupeLeCalendrier()),
        ));
    }
}
