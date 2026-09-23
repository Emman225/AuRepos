<?php

namespace App\Http\Controllers\Api\V1\Apporteur;

use App\Http\Controllers\Api\V1\Apporteur\Concerns\ResoutLApporteurConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Apporteur\CommissionResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace apporteur › SES commissions (lecture seule) : référence du séjour, montant, date. */
final class CommissionsController extends Controller
{
    use ResoutLApporteurConnecte;

    public function index(Request $request): JsonResponse
    {
        $apporteur = $this->monApporteur($request);

        return ReponseApi::succes(CommissionResource::collection(
            $apporteur->commissions()->with('sejour')->orderByDesc('id')->get(),
        ));
    }
}
