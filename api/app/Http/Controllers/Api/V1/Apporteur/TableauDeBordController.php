<?php

namespace App\Http\Controllers\Api\V1\Apporteur;

use App\Domain\Partenaires\Models\CommissionApporteur;
use App\Domain\Partenaires\Services\Parrainage;
use App\Http\Controllers\Api\V1\Apporteur\Concerns\ResoutLApporteurConnecte;
use App\Http\Controllers\Controller;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Tableau de bord de l'apporteur (lecture seule) : ses filleuls, ses commissions, son solde dû. */
final class TableauDeBordController extends Controller
{
    use ResoutLApporteurConnecte;

    public function index(Request $request, Parrainage $parrainage): JsonResponse
    {
        $apporteur = $this->monApporteur($request);

        return ReponseApi::succes([
            'nombre_filleuls' => $apporteur->filleuls()->count(),
            'nombre_commissions' => CommissionApporteur::query()->where('apporteur_id', $apporteur->id)->count(),
            'solde_du' => $parrainage->soldeDu($apporteur),
        ]);
    }
}
