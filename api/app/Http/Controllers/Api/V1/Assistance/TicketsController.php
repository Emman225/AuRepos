<?php

namespace App\Http\Controllers\Api\V1\Assistance;

use App\Domain\Assistance\Enums\EtatDuTicket;
use App\Domain\Assistance\Models\TicketAssistance;
use App\Domain\Assistance\Services\TicketsAssistance;
use App\Domain\Comptes\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Assistance\TicketAssistanceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Espace assistance (self-service, CdC § 6.1) : Profil::AgentAssistance — jusqu'ici sans
 * aucune route (audit du 22/09/2026). Traite les tickets soulevés par les clients pendant
 * leur séjour : liste, réponse, fermeture.
 */
final class TicketsController extends Controller
{
    private const RELATIONS = ['sejour.logement', 'client'];

    public function __construct(private readonly TicketsAssistance $tickets) {}

    public function index(Request $request): JsonResponse
    {
        $filtres = $request->validate(['statut' => ['nullable', Rule::enum(EtatDuTicket::class)]]);

        $tickets = TicketAssistance::query()->with(self::RELATIONS)
            ->when(
                $filtres['statut'] ?? null,
                fn (Builder $q, string $v) => $q->where('statut', $v),
                fn (Builder $q) => $q->whereIn('statut', [EtatDuTicket::Ouvert->value, EtatDuTicket::EnCours->value]),
            )
            ->orderBy('created_at')->get();

        return ReponseApi::succes(TicketAssistanceResource::collection($tickets));
    }

    public function repondre(Request $request, TicketAssistance $ticket): JsonResponse
    {
        $saisie = $request->validate(['reponse' => ['required', 'string', 'min:5', 'max:2000']], [], ['reponse' => 'réponse']);

        $ticket = $this->tickets->repondre($ticket, $this->moi($request), (string) $saisie['reponse']);

        return ReponseApi::succes(new TicketAssistanceResource($ticket->load(self::RELATIONS)), 'Réponse envoyée.');
    }

    public function fermer(Request $request, TicketAssistance $ticket): JsonResponse
    {
        $saisie = $request->validate(['reponse' => ['nullable', 'string', 'min:5', 'max:2000']], [], ['reponse' => 'réponse']);

        $ticket = $this->tickets->fermer($ticket, $this->moi($request), $saisie['reponse'] ?? null);

        return ReponseApi::succes(new TicketAssistanceResource($ticket->load(self::RELATIONS)), 'Ticket fermé.');
    }

    private function moi(Request $request): User
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return $utilisateur;
    }
}
