<?php

namespace App\Http\Controllers\Api\V1\Livreur;

use App\Domain\Repas\Services\GestionDesCommandes;
use App\Http\Controllers\Api\V1\Livreur\Concerns\ResoutLeLivreurConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace livreur › ses gains (CdC — « consulte ses gains »). */
final class GainsController extends Controller
{
    use ResoutLeLivreurConnecte;

    public function index(Request $request, GestionDesCommandes $commandes): JsonResponse
    {
        return ReponseApi::succes($commandes->gainsDuLivreur($this->monLivreur($request)));
    }
}
