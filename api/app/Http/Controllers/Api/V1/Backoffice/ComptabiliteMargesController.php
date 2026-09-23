<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\Marges;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Marges par séjour et par transfert, bloc « Bénéfices de l'entreprise », cautions (CdC § 9.3, P3-CPT-03). */
final class ComptabiliteMargesController extends Controller
{
    public function __construct(private readonly Marges $marges) {}

    public function parSejour(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);

        return ReponseApi::succes($this->marges->parSejour($du, $au));
    }

    public function parTransfert(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);

        return ReponseApi::succes($this->marges->parTransfert($du, $au));
    }

    public function recapitulatif(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);

        return ReponseApi::succes($this->marges->recapitulatif($du, $au));
    }

    public function etatDesCautions(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);

        return ReponseApi::succes($this->marges->etatDesCautions($du, $au));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periode(Request $request): array
    {
        $periode = FiltrePeriode::depuis($request);

        return [$periode->du ?? Carbon::today()->startOfMonth(), $periode->au ?? Carbon::today()->endOfMonth()];
    }
}
