<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\EtatsFiscaux;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** TVA, taxe de développement touristique et taxe de séjour (CdC § 9.3, P3-CPT-01). */
final class ComptabiliteFiscaliteController extends Controller
{
    public function __construct(private readonly EtatsFiscaux $etats) {}

    public function tva(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);
        $residenceId = $request->integer('residence_id') ?: null;

        return ReponseApi::succes($this->etats->tva($du, $au, $residenceId));
    }

    public function tdt(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);
        $residenceId = $request->integer('residence_id') ?: null;

        return ReponseApi::succes($this->etats->tdt($du, $au, $residenceId));
    }

    public function taxeDeSejour(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);

        return ReponseApi::succes($this->etats->taxeDeSejour($du, $au));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periode(Request $request): array
    {
        $periode = FiltrePeriode::depuis($request);

        return [$periode->du ?? Carbon::today()->startOfMonth(), $periode->au ?? Carbon::today()->endOfMonth()];
    }
}
