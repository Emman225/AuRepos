<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Devis;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tableau de bord (CdC § 6) : les compteurs qui amorcent l'exploitation du jour — mêmes
 * files que les écrans « Réservations en attente », « Arrivées / Départs du jour », « Séjours
 * en cours » et « Devis » (§ 6.1), juste comptées plutôt que listées. Un gestionnaire ne
 * voit que ses résidences (§ 9.5), comme partout ailleurs dans le back office.
 */
final class TableauDeBordController extends Controller
{
    public function __construct(private readonly PerimetreGestionnaire $perimetre) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $autorisees = $this->perimetre->residencesAutorisees($utilisateur);
        $aujourdHui = Carbon::today()->toDateString();

        $sejours = fn (): Builder => Sejour::query()->when(
            $autorisees !== null,
            fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->whereIn('residence_id', $autorisees ?? [])),
        );

        return ReponseApi::succes([
            'reservations_en_attente' => $sejours()->where('etat', EtatDuSejour::Demande)->count(),
            'arrivees_du_jour' => $sejours()->where('etat', EtatDuSejour::Confirme)->whereDate('arrivee', $aujourdHui)->count(),
            'departs_du_jour' => $sejours()->where('etat', EtatDuSejour::Arrive)->whereDate('depart', $aujourdHui)->count(),
            'sejours_en_cours' => $sejours()->where('etat', EtatDuSejour::Arrive)->count(),
            'devis_en_attente' => Devis::query()->when(
                $autorisees !== null,
                fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->whereIn('residence_id', $autorisees ?? [])),
            )->where('etat', 'en_attente')->count(),
        ]);
    }
}
