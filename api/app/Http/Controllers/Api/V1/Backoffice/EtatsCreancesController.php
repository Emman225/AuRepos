<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\CreancesEtDettes;
use App\Domain\Parametres\Services\Parametres;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Créances et dettes (CdC § 9.1, § 9.2) : menu « État » du back office, réservé aux
 * administrateurs.
 */
final class EtatsCreancesController extends Controller
{
    public function __construct(
        private readonly CreancesEtDettes $creances,
        private readonly Parametres $parametres,
    ) {}

    public function etatClientATerme(): JsonResponse
    {
        return ReponseApi::succes($this->creances->etatClientATerme());
    }

    public function balanceAgee(): JsonResponse
    {
        return ReponseApi::succes($this->creances->balanceAgee());
    }

    public function recapitulatifCreances(): JsonResponse
    {
        return ReponseApi::succes($this->creances->recapitulatifCreances());
    }

    public function recapitulatifDettes(): JsonResponse
    {
        return ReponseApi::succes($this->creances->recapitulatifDettes());
    }

    public function paiementFilleul(Request $request): JsonResponse
    {
        $periode = FiltrePeriode::depuis($request);

        return ReponseApi::succes($this->creances->etatPaiementFilleul($periode->du, $periode->au));
    }

    /** Délai et seuil paramétrables (CdC § 9.1, § 12). */
    public function relances(): JsonResponse
    {
        $delai = (int) $this->parametres->valeur('creances.relance_delai_jours');
        $seuil = (int) $this->parametres->valeur('creances.relance_seuil_montant');

        return ReponseApi::succes($this->creances->relances($delai, $seuil));
    }
}
