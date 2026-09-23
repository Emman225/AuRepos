<?php

namespace App\Http\Controllers\Api\V1\Proprietaire;

use App\Domain\Catalogue\Models\Residence;
use App\Http\Controllers\Api\V1\Proprietaire\Concerns\ResoutLeProprietaireConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Proprietaire\LogementResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace propriétaire › Logements d'une de SES résidences (lecture seule, CdC § 7). */
final class LogementsController extends Controller
{
    use ResoutLeProprietaireConnecte;

    public function index(Request $request, Residence $residence): JsonResponse
    {
        $this->verifierAppartenance($request, $residence);

        return ReponseApi::succes(LogementResource::collection(
            $residence->logements()->with('type')->orderBy('nom')->get(),
        ));
    }

    /** La résidence d'un autre propriétaire N'EXISTE PAS pour moi : 404, jamais 403. */
    private function verifierAppartenance(Request $request, Residence $residence): void
    {
        if ($residence->proprietaire_id !== $this->monProprietaire($request)->id) {
            abort(404);
        }
    }
}
