<?php

namespace App\Http\Controllers\Api\V1\Chauffeur;

use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Http\Controllers\Api\V1\Chauffeur\Concerns\ResoutLeChauffeurConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace chauffeur › mes gains : total déjà gagné (transferts terminés) et ce qui reste dû. */
final class GainsController extends Controller
{
    use ResoutLeChauffeurConnecte;

    public function index(Request $request, GestionDesTransferts $gestion): JsonResponse
    {
        $chauffeur = $this->monChauffeur($request);

        return ReponseApi::succes($gestion->mesGains($chauffeur));
    }
}
