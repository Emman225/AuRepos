<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Comptabilite\Services\GrandsLivres;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use App\Support\Listes\FiltrePeriode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Grands livres par catégorie de tiers (CdC § 9.3, P3-CPT-05).
 *
 * `{categorie}` n'est pas revalidé ici : une catégorie inconnue lève une `ErreurMetier` (422)
 * depuis `GrandsLivres::parCategorie()`, sur le même modèle que le reste du back office.
 */
final class ComptabiliteGrandsLivresController extends Controller
{
    public function __construct(private readonly GrandsLivres $grandsLivres) {}

    public function index(Request $request, string $categorie): JsonResponse
    {
        $periode = FiltrePeriode::depuis($request);

        return ReponseApi::succes([
            'categorie' => $categorie,
            ...$this->grandsLivres->parCategorie($categorie, $periode->du, $periode->au),
        ]);
    }
}
