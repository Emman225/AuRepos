<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\EtatRetenues;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use App\Support\Exports\ExportDeListe;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/** État des retenues à la source, export pour la déclaration DGI (CdC § 9.3, P3-CPT-02). */
final class ComptabiliteRetenuesController extends Controller
{
    public function __construct(private readonly EtatRetenues $retenues) {}

    public function index(Request $request): JsonResponse
    {
        [$du, $au] = $this->periode($request);

        return ReponseApi::succes($this->retenues->parBeneficiaireEtMois($du, $au));
    }

    public function exporter(Request $request): Response
    {
        $filtres = $request->validate(['format' => ['required', Rule::in(['xlsx', 'docx', 'pdf'])]]);
        [$du, $au] = $this->periode($request);
        $etat = $this->retenues->parBeneficiaireEtMois($du, $au);

        $export = new ExportDeListe('Retenues à la source', [
            'beneficiaire' => 'Bénéficiaire', 'mois' => 'Mois', 'regime' => 'Régime', 'taux_pourcent' => 'Taux (%)',
            'montant_brut' => 'Montant brut', 'retenue' => 'Retenue', 'net_verse' => 'Net versé',
        ], $etat['lignes']);

        return $export->reponse($filtres['format']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periode(Request $request): array
    {
        $periode = FiltrePeriode::depuis($request);

        return [$periode->du ?? Carbon::today()->startOfMonth(), $periode->au ?? Carbon::today()->endOfMonth()];
    }
}
