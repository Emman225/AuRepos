<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Tableau de bord propriétaire (lecture seule, CdC § 7) : des compteurs réels, agrégés
 * depuis SES résidences. Aucun indicateur financier ici — aucune donnée de reversement
 * ou de commission propriétaire n'existe encore dans le code, on ne l'invente pas.
 */
final class TableauDeBordController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function index(Request $request): JsonResponse
    {
        $proprietaire = $this->monProprietaire($request);
        $residenceIds = $proprietaire->residences()->pluck('id');
        $aujourdHui = Carbon::today();

        $sejours = fn (): Builder => Sejour::query()
            ->whereHas('logement', fn (Builder $q) => $q->whereIn('residence_id', $residenceIds));

        return ReponseApi::succes([
            'nombre_residences' => $residenceIds->count(),
            'nombre_logements' => Logement::query()->whereIn('residence_id', $residenceIds)->count(),
            'sejours_en_cours' => $sejours()->where('etat', EtatDuSejour::Arrive)->count(),
            'arrivees_sous_7_jours' => $sejours()->where('etat', EtatDuSejour::Confirme)
                ->whereBetween('arrivee', [$aujourdHui->toDateString(), $aujourdHui->copy()->addDays(7)->toDateString()])
                ->count(),
        ]);
    }
}
