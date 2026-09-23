<?php

namespace App\Http\Controllers\Api\V1\Chauffeur;

use App\Domain\Transferts\Enums\EtatDuTransfert;
use App\Domain\Transferts\Models\Transfert;
use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Http\Controllers\Api\V1\Chauffeur\Concerns\ResoutLeChauffeurConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tableau de bord du chauffeur (lecture seule) : transferts affectés en attente, gains. */
final class TableauDeBordController extends Controller
{
    use ResoutLeChauffeurConnecte;

    public function index(Request $request, GestionDesTransferts $gestion): JsonResponse
    {
        $chauffeur = $this->monChauffeur($request);

        return ReponseApi::succes([
            'nombre_transferts_affectes' => Transfert::query()
                ->where('chauffeur_id', $chauffeur->id)->where('etat', EtatDuTransfert::Affecte)->count(),
            ...$gestion->mesGains($chauffeur),
        ]);
    }
}
