<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Transferts\Enums\EtatDuTransfert;
use App\Domain\Transferts\Models\Chauffeur;
use App\Domain\Transferts\Models\Transfert;
use App\Domain\Transferts\Models\Vehicule;
use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\TransfertResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Transferts en attente / traités (CdC § 6.6) : affectation chauffeur et véhicule, code de
 * prise en charge remis au client — jamais lu ici (CdC § 11). Réservé à la gestion
 * quotidienne (mêmes profils que réservations/planning).
 */
final class TransfertsController extends Controller
{
    private const RELATIONS = ['sejour', 'commune', 'typeVehiculeSouhaite', 'chauffeur.utilisateur', 'vehicule'];

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate([
            'etat' => ['nullable', Rule::enum(EtatDuTransfert::class)],
            'par_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = Transfert::query()->with(self::RELATIONS)
            ->when($filtres['etat'] ?? null, fn (Builder $q, string $v) => $q->where('etat', $v))
            ->orderByDesc('id')
            ->paginate((int) ($filtres['par_page'] ?? 25));

        return ReponseApi::page($page, TransfertResource::class);
    }

    public function affecter(Request $request, Transfert $transfert, GestionDesTransferts $gestion): JsonResponse
    {
        $saisie = $request->validate([
            'chauffeur_id' => ['required', 'integer', Rule::exists('chauffeurs', 'id')],
            'vehicule_id' => ['required', 'integer', Rule::exists('vehicules', 'id')],
            'montant_verse_au_chauffeur' => ['required', 'integer', 'min:0', 'max:100000000'],
        ], [], [
            'chauffeur_id' => 'chauffeur', 'vehicule_id' => 'véhicule', 'montant_verse_au_chauffeur' => 'montant versé au chauffeur',
        ]);

        $chauffeur = Chauffeur::findOrFail($saisie['chauffeur_id']);
        $vehicule = Vehicule::findOrFail($saisie['vehicule_id']);

        $transfert = $gestion->affecter($transfert, $chauffeur, $vehicule, (int) $saisie['montant_verse_au_chauffeur']);

        return ReponseApi::succes(
            new TransfertResource($transfert->load(self::RELATIONS)),
            'Transfert affecté. Le code de prise en charge a été envoyé au client.',
        );
    }

    public function annuler(Transfert $transfert, GestionDesTransferts $gestion): JsonResponse
    {
        $transfert = $gestion->annuler($transfert);

        return ReponseApi::succes(new TransfertResource($transfert->load(self::RELATIONS)), 'Transfert annulé.');
    }
}
