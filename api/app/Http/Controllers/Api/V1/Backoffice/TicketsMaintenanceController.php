<?php

namespace App\Http\Controllers\Api\V1\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Comptes\Models\User;
use App\Domain\Maintenance\Enums\ImputationCout;
use App\Domain\Maintenance\Enums\UrgenceTicket;
use App\Domain\Maintenance\Models\TicketMaintenance;
use App\Domain\Maintenance\Services\TicketsMaintenance;
use App\Http\Controllers\Controller;
use App\Http\Resources\Backoffice\TicketMaintenanceResource;
use App\Support\Api\ReponseApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Tickets de maintenance d'un logement (P2-MNT-01, CdC § 6.4) : signalement direct (l'anomalie
 * remontée en clôture de mission de ménage passe par Missions::terminer, pas par cet écran).
 */
final class TicketsMaintenanceController extends Controller
{
    public function __construct(private readonly TicketsMaintenance $tickets) {}

    public function index(Residence $residence, Logement $logement): JsonResponse
    {
        $tickets = TicketMaintenance::query()->where('logement_id', $logement->id)->orderByDesc('created_at')->get();

        return ReponseApi::succes(TicketMaintenanceResource::collection($tickets));
    }

    public function creer(Request $request, Residence $residence, Logement $logement): JsonResponse
    {
        $saisie = $request->validate([
            'urgence' => ['required', Rule::enum(UrgenceTicket::class)],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'technicien_nom' => ['nullable', 'string', 'max:150'],
            'technicien_contact' => ['nullable', 'string', 'max:150'],
            'indisponible_jusquau' => ['nullable', 'date', 'after_or_equal:today', 'required_if:urgence,bloquante'],
        ], [], [
            'urgence' => 'urgence', 'description' => 'description', 'technicien_nom' => 'nom du technicien',
            'technicien_contact' => 'contact du technicien', 'indisponible_jusquau' => 'indisponible jusqu’au',
        ]);

        /** @var User $auteur */
        $auteur = $request->user();
        $ticket = $this->tickets->signaler(
            $logement, UrgenceTicket::from($saisie['urgence']), $saisie['description'],
            $saisie['technicien_nom'] ?? null, $saisie['technicien_contact'] ?? null, $auteur,
            isset($saisie['indisponible_jusquau']) ? Carbon::parse($saisie['indisponible_jusquau']) : null,
        );

        return ReponseApi::cree(new TicketMaintenanceResource($ticket->load('logement')), 'Ticket de maintenance créé.');
    }

    public function affecterUnTechnicien(Request $request, Residence $residence, Logement $logement, TicketMaintenance $ticket): JsonResponse
    {
        $this->exigerAppartenance($logement, $ticket);
        $saisie = $request->validate([
            'technicien_nom' => ['required', 'string', 'max:150'],
            'technicien_contact' => ['nullable', 'string', 'max:150'],
        ], [], ['technicien_nom' => 'nom du technicien', 'technicien_contact' => 'contact du technicien']);

        $ticket = $this->tickets->affecterUnTechnicien($ticket, $saisie['technicien_nom'], $saisie['technicien_contact'] ?? null);

        return ReponseApi::succes(new TicketMaintenanceResource($ticket->load('logement')), 'Technicien affecté.');
    }

    public function imputerUnCout(Request $request, Residence $residence, Logement $logement, TicketMaintenance $ticket): JsonResponse
    {
        $this->exigerAppartenance($logement, $ticket);
        $saisie = $request->validate([
            'montant' => ['required', 'integer', 'min:0', 'max:100000000'],
            'impute_a' => ['required', Rule::enum(ImputationCout::class)],
        ], [], ['montant' => 'montant', 'impute_a' => 'imputation']);

        $ticket = $this->tickets->imputerUnCout($ticket, (int) $saisie['montant'], ImputationCout::from($saisie['impute_a']));

        return ReponseApi::succes(new TicketMaintenanceResource($ticket->load('logement')), 'Coût imputé.');
    }

    public function resoudre(Request $request, Residence $residence, Logement $logement, TicketMaintenance $ticket): JsonResponse
    {
        $this->exigerAppartenance($logement, $ticket);

        /** @var User $auteur */
        $auteur = $request->user();
        $ticket = $this->tickets->resoudre($ticket, $auteur);

        return ReponseApi::succes(new TicketMaintenanceResource($ticket->load('logement')), 'Ticket résolu : le logement redevient réservable s’il était bloqué.');
    }

    /** Un ticket d'un autre logement N'EXISTE PAS ici : 404, jamais 403 — même patron que {vehicule} sous {chauffeur}. */
    private function exigerAppartenance(Logement $logement, TicketMaintenance $ticket): void
    {
        if ($ticket->logement_id !== $logement->id) {
            abort(404);
        }
    }
}
