<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Fidelite\Services\PointsDeFidelite;
use App\Domain\Sejours\Services\Calendrier;
use App\Domain\Sejours\Services\RechercheDeLogements;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Domain\Tarification\Calcul\DemandeDeCalcul;
use App\Domain\Tarification\Services\CodesPromo;
use App\Domain\Tarification\Services\PrixNegocies;
use App\Domain\Tarification\Services\Tarifs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tarification\EstimationRequest;
use App\Http\Resources\Publique\LogementPublicResource;
use App\Http\Resources\Publique\VignetteLogementResource;
use App\Support\Api\ErreurMetier;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Catalogue public. La recherche de disponibilité arrive avec P1-RES-02. */
final class CatalogueController extends Controller
{
    public function logement(string $reference): JsonResponse
    {
        return ReponseApi::succes(new LogementPublicResource($this->publie($reference)));
    }

    /** Calendrier de disponibilité d'un mois (CdC § 5.1) : les nuits déjà occupées, rien de plus. */
    public function disponibilite(Request $request, string $reference, Calendrier $calendrier): JsonResponse
    {
        $mois = $request->validate([
            'mois' => ['required', 'date_format:Y-m'],
        ])['mois'];

        $logement = $this->publie($reference);
        $debut = Carbon::createFromFormat('Y-m-d', $mois.'-01')->startOfMonth();

        return ReponseApi::succes([
            'mois' => $debut->format('Y-m'),
            'jours_occupes' => $calendrier->joursOccupes($logement, $debut, $debut->copy()->addMonthNoOverflow()),
        ]);
    }

    /**
     * Recherche du site public (CdC § 5.1). Sans dates : ce qui est libre ce soir, rangé par commune puis quartier.
     */
    public function rechercher(Request $request, RechercheDeLogements $recherche, Tarifs $tarifs): JsonResponse
    {
        $saisie = $request->validate([
            'arrivee' => ['nullable', 'date', 'after_or_equal:today', 'required_with:depart'],
            'depart' => ['nullable', 'date', 'after:arrivee', 'required_with:arrivee'],
            'adultes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'enfants' => ['nullable', 'integer', 'min:0', 'max:60'],
            'commune_id' => ['nullable', 'integer'],
            'quartier_id' => ['nullable', 'integer'],
            'type_logement_id' => ['nullable', 'integer'],
            'budget_max' => ['nullable', 'integer', 'min:1'],
            'equipements' => ['nullable', 'array', 'max:20'],
            'equipements.*' => ['integer'],
            'par_page' => ['nullable', 'integer', 'min:6', 'max:48'],
        ], ['arrivee.after_or_equal' => 'La date d’arrivée ne peut pas être passée.'], [
            'arrivee' => 'date d’arrivée', 'depart' => 'date de départ', 'adultes' => 'nombre d’adultes', 'budget_max' => 'budget par nuit',
        ]);

        $avecDates = isset($saisie['arrivee']);
        $arrivee = $avecDates ? Carbon::parse($saisie['arrivee']) : Carbon::today();
        $depart = $avecDates ? Carbon::parse($saisie['depart']) : Carbon::tomorrow();
        $occupants = isset($saisie['adultes']) ? (int) $saisie['adultes'] + (int) ($saisie['enfants'] ?? 0) : null;

        $page = $recherche->requete($arrivee, $depart, [
            'commune_id' => $saisie['commune_id'] ?? null, 'quartier_id' => $saisie['quartier_id'] ?? null,
            'type_logement_id' => $saisie['type_logement_id'] ?? null, 'occupants' => $occupants,
            'budget_max' => $saisie['budget_max'] ?? null, 'equipements' => array_values(array_map('intval', $saisie['equipements'] ?? [])) ?: null,
        ])
            ->join('residences', 'residences.id', '=', 'logements.residence_id')
            ->join('quartiers', 'quartiers.id', '=', 'residences.quartier_id')
            ->join('communes', 'communes.id', '=', 'quartiers.commune_id')
            ->orderBy('communes.nom')->orderBy('quartiers.nom')->orderBy('logements.prix_vente')->orderBy('logements.id')
            ->with(['type', 'photos', 'residence.quartier.commune', 'avisPublies'])
            ->paginate((int) ($saisie['par_page'] ?? 24));

        // Le prix de chaque vignette est celui du séjour demandé (grille, saison), pas un prix d'appel.
        foreach ($page->items() as $logement) {
            $nuitees = $tarifs->nuitees($logement, $arrivee, $depart);
            $logement->setAttribute('prix_par_nuit_calcule', intdiv(array_sum(array_column($nuitees, 'tarif')), count($nuitees)));
        }

        return ReponseApi::succes([
            'avec_dates' => $avecDates,
            'arrivee' => $arrivee->toDateString(), 'depart' => $depart->toDateString(),
            'elements' => VignetteLogementResource::collection($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'par_page' => $page->perPage(), 'total' => $page->total(), 'derniere_page' => $page->lastPage()],
        ]);
    }

    /**
     * Prix d'un séjour, recalculé par le serveur pendant la saisie (CdC § 5.2). Le client n'envoie
     * que des dates et des occupants ; la disponibilité sera contrôlée à la réservation (P1-RES).
     */
    public function estimer(
        EstimationRequest $request, string $reference, CalculDuSejour $calcul, Calendrier $calendrier,
        PrixNegocies $prixNegocies, CodesPromo $codesPromo,
    ): JsonResponse {
        $logement = $this->publie($reference);
        $client = $request->user();

        // Le prix négocié du client CONNECTÉ prime sur toute la grille (CdC § 7.3), même en simple estimation.
        $tarifNegocie = $client === null ? null : $prixNegocies->pour($client, $logement->type_logement_id);

        $codePromo = null;
        $motifCodePromo = null;
        if (filled($request->string('code_promo')->value())) {
            try {
                $codePromo = $codesPromo->verifier($request->string('code_promo')->value(), $logement->residence);
            } catch (ErreurMetier $e) {
                $motifCodePromo = $e->getMessage();
            }
        }

        $sansCodePromo = $calcul->calculer(new DemandeDeCalcul(
            logement: $logement,
            arrivee: Carbon::parse($request->string('arrivee')->value()),
            depart: Carbon::parse($request->string('depart')->value()),
            adultes: $request->integer('adultes'),
            enfants: $request->integer('enfants'),
            arriveeTardive: $request->boolean('arrivee_tardive'),
            departTardif: $request->boolean('depart_tardif'),
            tarifNegocieParNuit: $tarifNegocie,
        ));

        $devis = $codePromo === null ? $sansCodePromo : $calcul->calculer(new DemandeDeCalcul(
            logement: $logement,
            arrivee: Carbon::parse($request->string('arrivee')->value()),
            depart: Carbon::parse($request->string('depart')->value()),
            adultes: $request->integer('adultes'),
            enfants: $request->integer('enfants'),
            arriveeTardive: $request->boolean('arrivee_tardive'),
            departTardif: $request->boolean('depart_tardif'),
            reductions: [['libelle' => 'Code promo « '.$codePromo->code.' »', 'montant' => $codesPromo->calculerLaReduction($codePromo, $sansCodePromo->hebergementNetHt)]],
            tarifNegocieParNuit: $tarifNegocie,
        ));

        $libre = $calendrier->estLibre($logement, Carbon::parse($request->string('arrivee')->value()), Carbon::parse($request->string('depart')->value()));

        // Ce que le client CONNECTÉ pourrait retirer avec ses points sur ce séjour.
        $fidelite = $client === null ? null : app(PointsDeFidelite::class)
            ->utilisablesSur($client->getAuthIdentifier(), $sansCodePromo->hebergementNetHt, PHP_INT_MAX);

        // Indication seulement : la disponibilité est GARANTIE au moment de réserver, pas ici.
        return ReponseApi::succes([
            ...$devis->toArray(), 'disponible' => $libre, 'fidelite' => $fidelite,
            'code_promo' => ['valide' => $codePromo !== null, 'motif' => $motifCodePromo],
        ]);
    }

    private function publie(string $reference): Logement
    {
        // Un logement non publié, ou d'une résidence désactivée, N'EXISTE PAS pour le public : 404, pas 403.
        $logement = Logement::query()
            ->where('reference', $reference)
            ->where('etat_publication', EtatPublication::Publie)
            ->whereRelation('residence', 'active', true)
            ->with(['type', 'equipements', 'photos', 'residence.equipements', 'residence.quartier.commune', 'avisPublies.sejour.client'])
            ->firstOrFail();

        return $logement;
    }
}
