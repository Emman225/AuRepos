<?php

namespace App\Http\Controllers\Api\V1\Chauffeur;

use App\Domain\Comptes\Models\User;
use App\Domain\Transferts\Models\Transfert;
use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Http\Controllers\Api\V1\Chauffeur\Concerns\ResoutLeChauffeurConnecte;
use App\Http\Controllers\Controller;
use App\Http\Resources\Chauffeur\TransfertResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Espace chauffeur › mes transferts (CdC § 6.6) : SES transferts, jamais ceux d'un autre
 * chauffeur. Le code de prise en charge se SAISIT ici, il ne s'y lit jamais (CdC § 11).
 */
final class TransfertsController extends Controller
{
    use ResoutLeChauffeurConnecte;

    private const RELATIONS = ['sejour', 'commune', 'vehicule'];

    public function index(Request $request): JsonResponse
    {
        $chauffeur = $this->monChauffeur($request);

        $transferts = $chauffeur->transferts()->with(self::RELATIONS)->orderByDesc('date_heure_prevue')->get();

        return ReponseApi::succes(TransfertResource::collection($transferts));
    }

    public function cloturer(Request $request, int $transfert, GestionDesTransferts $gestion): JsonResponse
    {
        $chauffeur = $this->monChauffeur($request);

        // Le transfert d'un autre chauffeur N'EXISTE PAS pour moi : 404, jamais 403.
        $ligne = Transfert::query()->where('id', $transfert)->where('chauffeur_id', $chauffeur->id)->firstOrFail();

        $saisie = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'code de prise en charge']);

        /** @var User $utilisateur */
        $utilisateur = $request->user();
        $ligne = $gestion->cloturerParCode($ligne, $saisie['code'], $utilisateur);

        return ReponseApi::succes(new TransfertResource($ligne->load(self::RELATIONS)), 'Transfert clôturé.');
    }
}
