<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\PlanningLogementResource;
use App\Http\Resources\Backoffice\PlanningSejourResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Planning (grille logements × jours, façon PMS) : agrège en un seul appel les logements et les
 * séjours qui occupent le calendrier sur une période, pour l'écran back-office React à venir.
 * Lecture seule — aucune règle métier nouvelle, aucune nouvelle table : un agrégat de ce que
 * « Réservations » (SejoursController) et « Catalogue » (LogementsController) exposent déjà,
 * cloisonné au périmètre du gestionnaire comme eux (CdC § 9.5).
 */
final class PlanningController extends Controller
{
    public function __construct(private readonly PerimetreGestionnaire $perimetre) {}

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

        $sejours = Sejour::query()
            ->with('client')
            ->whereIn('logement_id', $logements->pluck('id'))
            ->whereIn('etat', $this->etatsQuiOccupentLeCalendrier())
            // Chevauchement de deux intervalles « date de début incluse, date de fin exclue »,
            // comme le fait déjà Calendrier::estLibre() pour la disponibilité : le séjour tient
            // [arrivee, depart[, la période demandée [du, au] devient donc [du, au + 1 jour[.
            ->whereRaw(
                'daterange(arrivee, depart, \'[)\') && daterange(?, ?, \'[)\')',
                [$filtres['du'], Carbon::parse($filtres['au'])->addDay()->toDateString()],
            )
            ->get();

        return ReponseApi::succes([
            'logements' => PlanningLogementResource::collection($logements),
            'sejours' => PlanningSejourResource::collection($sejours),
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
