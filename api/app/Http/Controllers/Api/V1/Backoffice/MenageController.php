<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Enums\EtatMenage;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Services\PerimetreGestionnaire;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Enums\StatutDeMission;
use App\Domain\Exploitation\Models\Mission;
use App\Domain\Exploitation\Services\Missions;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\LogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tableau de bord gouvernante (P2-MEN-02, CdC § 6.4) : missions par état, logements par état
 * de propreté, et la validation « logement prêt » — cloisonné par résidence comme le reste
 * de l'exploitation (App\Domain\Catalogue\Services\PerimetreGestionnaire).
 */
final class MenageController extends Controller
{
    public function __construct(private readonly PerimetreGestionnaire $perimetre, private readonly Missions $missions) {}

    public function tableauDeBord(Request $request): JsonResponse
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $autorisees = $this->perimetre->residencesAutorisees($utilisateur);

        $missions = fn (): Builder => Mission::query()->when(
            $autorisees !== null,
            fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->whereIn('residence_id', $autorisees ?? [])),
        );
        $logements = fn (): Builder => Logement::query()->when(
            $autorisees !== null, fn (Builder $q) => $q->whereIn('residence_id', $autorisees ?? []),
        );

        return ReponseApi::succes([
            'missions_par_statut' => collect(StatutDeMission::cases())
                ->mapWithKeys(fn (StatutDeMission $s) => [$s->value => $missions()->where('statut', $s->value)->count()]),
            'logements_par_etat_menage' => collect(EtatMenage::cases())
                ->mapWithKeys(fn (EtatMenage $e) => [$e->value => $logements()->where('etat_menage', $e->value)->count()])
                ->put('non_renseigne', $logements()->whereNull('etat_menage')->count()),
            'missions_en_retard' => $missions()->where('statut', '!=', StatutDeMission::Faite->value)->where('echeance', '<', now())->count(),
            'logements_a_valider' => LogementResource::collection(
                $logements()->where('etat_menage', EtatMenage::Propre->value)->with('residence')->orderBy('nom')->get(),
            ),
        ]);
    }

    /** Validation « logement prêt » (P2-MEN-02) : la gouvernante contrôle, le logement passe « contrôlé ». */
    public function validerLeLogement(Request $request, Logement $logement): JsonResponse
    {
        $this->exigerVisible($logement, $request);

        /** @var User $auteur */
        $auteur = $request->user();
        $logement = $this->missions->validerLeMenage($logement, $auteur);

        return ReponseApi::succes(new LogementResource($logement), 'Logement validé : prêt.');
    }

    /** Même règle que `CloisonnerResidence` (CdC § 9.5) : hors résidence autorisée, 404, jamais 403. */
    private function exigerVisible(Logement $logement, Request $request): void
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();
        if (! $this->perimetre->residenceVisible($logement->residence_id, $utilisateur)) {
            abort(404);
        }
    }
}
