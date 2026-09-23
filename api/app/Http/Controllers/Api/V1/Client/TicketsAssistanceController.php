<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Domain\Assistance\Services\TicketsAssistance;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assistance\TicketAssistanceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mon espace › Assistance (P2-AST-01, CdC § 6.1) : un ticket se soulève PENDANT un séjour
 * en cours, sur MON séjour uniquement.
 */
final class TicketsAssistanceController extends Controller
{
    public function __construct(private readonly TicketsAssistance $tickets) {}

    public function index(Request $request, string $reference): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);

        return ReponseApi::succes(TicketAssistanceResource::collection(
            $sejour->ticketsAssistance()->orderByDesc('id')->get(),
        ));
    }

    public function soumettre(Request $request, string $reference): JsonResponse
    {
        $sejour = $this->leMien($request, $reference);
        $saisie = $request->validate([
            'sujet' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ], [], ['sujet' => 'sujet', 'message' => 'message']);

        /** @var User $client */
        $client = $request->user();
        $ticket = $this->tickets->ouvrir($sejour, $client, (string) $saisie['sujet'], (string) $saisie['message']);

        return ReponseApi::cree(new TicketAssistanceResource($ticket), 'Ticket d’assistance envoyé : l’équipe assistance va vous répondre.');
    }

    private function leMien(Request $request, string $reference): Sejour
    {
        return Sejour::query()->where('reference', $reference)
            ->where('client_id', $request->user()?->getAuthIdentifier())
            ->firstOrFail();
    }
}
