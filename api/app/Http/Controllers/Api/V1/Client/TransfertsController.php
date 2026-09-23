<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Services\GestionDesTransferts;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\TransfertResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mon espace › Mes extras et transferts (CdC § 5.2, § 6.6) : demande PENDANT un séjour déjà
 * existant. Un client ne voit et ne touche que les transferts de SES séjours.
 */
final class TransfertsController extends Controller
{
    private const RELATIONS = ['commune', 'typeVehiculeSouhaite'];

    /**
     * Toujours scopée à UN séjour dont la propriété est déjà vérifiée (jamais une liste
     * transverse de tous les transferts du client) : le code de prise en charge, s'il existe,
     * peut donc y figurer en clair — même garantie que le code d'arrivée (CdC § 11).
     */
    public function index(Request $request, string $reference): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        $transferts = $sejour->transferts()->with(self::RELATIONS)->orderByDesc('id')->get();

        return ReponseApi::succes($transferts->map(fn ($t) => new TransfertResource($t, avecCode: true)));
    }

    public function demander(Request $request, string $reference, GestionDesTransferts $gestion): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        $saisie = $request->validate([
            'lieu_de_prise_en_charge' => ['required', 'string', 'max:255'],
            'commune_id' => ['required', 'integer', Rule::exists('communes', 'id')],
            'type_vehicule_souhaite_id' => ['required', 'integer', Rule::exists('types_vehicule', 'id')],
            'date_heure_prevue' => ['required', 'date', 'after:now'],
            'nombre_passagers' => ['required', 'integer', 'min:1', 'max:50'],
            'nombre_bagages' => ['nullable', 'integer', 'min:0', 'max:50'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'lieu_de_prise_en_charge' => 'lieu de prise en charge', 'commune_id' => 'commune',
            'type_vehicule_souhaite_id' => 'type de véhicule', 'date_heure_prevue' => 'date et heure prévues',
            'nombre_passagers' => 'nombre de passagers', 'nombre_bagages' => 'nombre de bagages', 'notes' => 'notes',
        ]);

        $transfert = $gestion->demander($sejour, $saisie);

        return ReponseApi::cree(new TransfertResource($transfert->load(self::RELATIONS)), 'Transfert demandé. Le prix est celui du barème en vigueur.');
    }

    /** Le séjour d'un autre client N'EXISTE PAS pour moi : 404, jamais 403. */
    private function leMien(Request $request, string $reference): Sejour
    {
        return Sejour::query()
            ->where('reference', $reference)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->firstOrFail();
    }
}
