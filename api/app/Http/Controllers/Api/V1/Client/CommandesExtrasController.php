<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Extras\Models\CommandeExtra;
use App\Domain\Extras\Models\Extra;
use App\Domain\Extras\Services\GestionDesExtras;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Client\CommandeExtraResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Mon espace › Mes extras (P2-EXT-01) : commande PENDANT un séjour déjà arrivé — même garde
 * que App\Http\Controllers\Api\V1\Client\TicketsAssistanceController. Un client ne voit et ne
 * touche que les commandes de SES séjours.
 */
final class CommandesExtrasController extends Controller
{
    private const RELATIONS = ['extra'];

    public function index(Request $request, string $reference): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        $commandes = CommandeExtra::query()->where('sejour_id', $sejour->id)->with(self::RELATIONS)->orderByDesc('id')->get();

        return ReponseApi::succes(CommandeExtraResource::collection($commandes));
    }

    public function commander(Request $request, string $reference, GestionDesExtras $gestion): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        $saisie = $request->validate([
            'extra_id' => ['required', 'integer', Rule::exists('extras', 'id')],
            'quantite' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['extra_id' => 'extra', 'quantite' => 'quantité', 'notes' => 'notes']);

        $extra = Extra::query()->where('actif', true)->findOrFail($saisie['extra_id']);

        $commande = $gestion->commander($sejour, $extra, (int) ($saisie['quantite'] ?? 1), null, $saisie['notes'] ?? null);

        return ReponseApi::cree(new CommandeExtraResource($commande->load(self::RELATIONS)), 'Extra commandé.');
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
