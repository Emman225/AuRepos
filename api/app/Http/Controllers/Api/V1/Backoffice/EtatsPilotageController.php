<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\EtatsPilotage;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * États de pilotage commercial (CdC § 9.1) : menu « État » du back office, réservé aux
 * administrateurs. Lecture seule — aucune de ces routes n'écrit quoi que ce soit.
 */
final class EtatsPilotageController extends Controller
{
    public function __construct(private readonly EtatsPilotage $etats) {}

    public function caDetaille(Request $request): JsonResponse
    {
        $periode = FiltrePeriode::depuis($request);
        $residenceId = $request->integer('residence_id') ?: null;

        return ReponseApi::succes($this->etats->caDetaille($periode->du, $periode->au, $residenceId));
    }

    public function caParResidenceEtType(Request $request): JsonResponse
    {
        $periode = FiltrePeriode::depuis($request);

        return ReponseApi::succes($this->etats->caParResidenceEtType($periode->du, $periode->au));
    }

    public function occupation(Request $request): JsonResponse
    {
        $periode = FiltrePeriode::depuis($request);
        $residenceId = $request->integer('residence_id') ?: null;

        return ReponseApi::succes($this->etats->occupationEtRevpar(
            $periode->du ?? Carbon::today()->startOfMonth(),
            $periode->au ?? Carbon::today()->endOfMonth(),
            $residenceId,
        ));
    }

    public function annulations(Request $request): JsonResponse
    {
        $periode = FiltrePeriode::depuis($request);

        return ReponseApi::succes($this->etats->annulationsEtNoShow(
            $periode->du ?? Carbon::today()->startOfMonth(),
            $periode->au ?? Carbon::today()->endOfMonth(),
        ));
    }

    public function disponibiliteResidences(): JsonResponse
    {
        return ReponseApi::succes($this->etats->disponibiliteResidences());
    }

    public function margeParResidence(Request $request): JsonResponse
    {
        $periode = FiltrePeriode::depuis($request);

        return ReponseApi::succes($this->etats->margeParResidence($periode->du, $periode->au));
    }

    public function previsionnel(): JsonResponse
    {
        return ReponseApi::succes($this->etats->previsionnel90Jours());
    }
}
