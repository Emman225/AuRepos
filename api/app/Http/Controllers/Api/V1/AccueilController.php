<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Contenu\Models\Banniere;
use App\Domain\Contenu\Models\Diapositive;
use App\Domain\Contenu\Models\Temoignage;
use App\Domain\Sejours\Enums\StatutAvis;
use App\Http\Controllers\Controller;
use App\Http\Resources\Publique\BanniereResource;
use App\Http\Resources\Publique\DiapositiveResource;
use App\Http\Resources\Publique\TemoignageResource;
use App\Http\Resources\Publique\VignetteLogementResource;
use App\Http\Resources\Publique\VignetteResidenceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;

/** Contenu de la page d'accueil du site public (CdC § 5.1). */
final class AccueilController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $misesEnAvant = Logement::query()
            ->where('mise_en_avant', true)
            ->where('etat_publication', EtatPublication::Publie)
            ->whereRelation('residence', 'active', true)
            ->with(['type', 'photos', 'residence.quartier.commune', 'avisPublies'])
            ->orderBy('id')
            ->limit(12)
            ->get();

        // Pas de dates recherchées sur la page d'accueil : le prix affiché est le prix de vente
        // administré, pas un tarif calculé sur un séjour précis (qui vient avec la recherche).
        foreach ($misesEnAvant as $logement) {
            $logement->setAttribute('prix_par_nuit_calcule', $logement->prix_vente);
        }

        $bannieres = Banniere::query()->where('actif', true)->orderBy('ordre')->orderBy('id')->get();
        $temoignages = Temoignage::query()->where('publie', true)->orderBy('ordre')->orderBy('id')->limit(12)->get();
        $carrousel = Diapositive::query()->where('actif', true)->orderBy('ordre')->orderBy('id')->get();

        $residencesMisesEnAvant = $this->residencesAvecNote()
            ->where('residences.mise_en_avant', true)
            ->limit(8)->get();

        $residencesMieuxNotees = $this->residencesAvecNote()
            ->havingRaw('COUNT(avis.id) >= 1')
            ->orderByDesc('note_moyenne_calc')
            ->limit(8)->get();

        return ReponseApi::succes([
            'carrousel' => DiapositiveResource::collection($carrousel),
            'mises_en_avant' => VignetteLogementResource::collection($misesEnAvant),
            'residences_mises_en_avant' => VignetteResidenceResource::collection($residencesMisesEnAvant),
            'residences_mieux_notees' => VignetteResidenceResource::collection($residencesMieuxNotees),
            'bannieres' => BanniereResource::collection($bannieres),
            'temoignages' => TemoignageResource::collection($temoignages),
        ]);
    }

    /**
     * Résidences actives, avec leur note moyenne calculée sur les avis PUBLIÉS (P2-AVI-01).
     *
     * @return Builder<Residence>
     */
    private function residencesAvecNote(): Builder
    {
        return Residence::query()
            ->select('residences.*')
            ->selectRaw('AVG(avis.note) as note_moyenne_calc')
            ->leftJoin('logements', 'logements.residence_id', '=', 'residences.id')
            ->leftJoin('sejours', 'sejours.logement_id', '=', 'logements.id')
            ->leftJoin('avis', fn (JoinClause $j) => $j->on('avis.sejour_id', '=', 'sejours.id')->where('avis.statut', StatutAvis::Publie->value))
            ->where('residences.active', true)
            ->groupBy('residences.id')
            ->with(['quartier.commune'])
            ->with([
                /** @param Relation<Logement, Residence, Logement> $q */
                'logements' => function (Relation $q): void {
                    $q->where('etat_publication', EtatPublication::Publie)->with('photos')->orderBy('prix_vente');
                },
            ]);
    }
}
