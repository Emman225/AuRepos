<?php

namespace App\Domain\Assistance\Services;

use App\Domain\Assistance\Enums\EtatDuTicket;
use App\Domain\Assistance\Models\TicketAssistance;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;

/**
 * Tickets d'assistance (P2-AST-01, CdC § 6.1) : un client en soulève un PENDANT un séjour
 * actif (état « arrivé »), l'espace assistance (Profil::AgentAssistance) répond et ferme.
 */
final class TicketsAssistance
{
    public function ouvrir(Sejour $sejour, User $client, string $sujet, string $message): TicketAssistance
    {
        if ($sejour->etat !== EtatDuSejour::Arrive) {
            throw new ErreurMetier('Un ticket d’assistance ne se soulève que pendant un séjour en cours.', 'sejour_non_arrive', 422);
        }

        return TicketAssistance::create([
            'sejour_id' => $sejour->id, 'client_id' => $client->id,
            'sujet' => trim($sujet), 'message' => trim($message),
        ])->refresh();
    }

    /** Une réponse ne ferme pas le ticket : elle le fait seulement passer « en cours » s'il était encore ouvert. */
    public function repondre(TicketAssistance $ticket, User $agent, string $reponse): TicketAssistance
    {
        $this->exigerPasFerme($ticket);

        $ticket->update([
            'reponse' => trim($reponse), 'traite_par' => $agent->id, 'traite_le' => now(),
            'statut' => $ticket->statut === EtatDuTicket::Ouvert ? EtatDuTicket::EnCours : $ticket->statut,
        ]);

        return $ticket->refresh();
    }

    public function fermer(TicketAssistance $ticket, User $agent, ?string $reponse = null): TicketAssistance
    {
        $this->exigerPasFerme($ticket);

        $ticket->update([
            'statut' => EtatDuTicket::Ferme, 'traite_par' => $agent->id, 'traite_le' => now(),
            'reponse' => $reponse ? trim($reponse) : $ticket->reponse,
        ]);

        return $ticket->refresh();
    }

    private function exigerPasFerme(TicketAssistance $ticket): void
    {
        if ($ticket->statut === EtatDuTicket::Ferme) {
            throw new ErreurMetier('Ce ticket est déjà fermé.', 'ticket_deja_ferme', 422);
        }
    }
}
