<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Models\Mission;
use App\Domain\Maintenance\Enums\EtatDuTicketMaintenance;
use App\Domain\Maintenance\Enums\ImputationCout;
use App\Domain\Maintenance\Enums\OrigineDuTicket;
use App\Domain\Maintenance\Enums\UrgenceTicket;
use App\Domain\Maintenance\Models\TicketMaintenance;
use App\Domain\Sejours\Models\BlocageCalendrier;
use App\Domain\Sejours\Services\Calendrier;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tickets de maintenance (P2-MNT-01, CdC § 6.4) : signalés directement ou remontés en
 * anomalie de clôture de mission de ménage (P2-MEN-03). Un ticket « bloquant » retire le
 * logement du calendrier en réutilisant le SEUL mécanisme de blocage existant
 * (App\Domain\Sejours\Services\Calendrier::bloquer, motif « maintenance ») — aucune
 * seconde notion d'« indisponibilité » n'est inventée ici.
 */
final class TicketsMaintenance
{
    public function __construct(private readonly Calendrier $calendrier) {}

    public function signaler(
        Logement $logement,
        UrgenceTicket $urgence,
        string $description,
        ?string $technicienNom,
        ?string $technicienContact,
        User $auteur,
        ?Carbon $indisponibleJusquau = null,
    ): TicketMaintenance {
        if ($urgence === UrgenceTicket::Bloquante && $indisponibleJusquau === null) {
            throw new ErreurMetier('Un ticket bloquant doit indiquer jusqu’à quand le logement est indisponible.', 'indisponibilite_obligatoire', 422);
        }

        return DB::transaction(function () use ($logement, $urgence, $description, $technicienNom, $technicienContact, $auteur, $indisponibleJusquau): TicketMaintenance {
            $ticket = TicketMaintenance::create([
                'logement_id' => $logement->id, 'origine' => OrigineDuTicket::Direct, 'urgence' => $urgence,
                'description' => trim($description), 'technicien_nom' => $technicienNom, 'technicien_contact' => $technicienContact,
                'signalee_par' => $auteur->id,
            ]);

            if ($urgence === UrgenceTicket::Bloquante && $indisponibleJusquau instanceof Carbon) {
                $blocage = $this->calendrier->bloquer(
                    $logement, Carbon::today(), $indisponibleJusquau, 'maintenance',
                    'Ticket de maintenance #'.$ticket->id.' : '.trim($description), $auteur,
                );
                $ticket->update(['blocage_calendrier_id' => $blocage->id, 'indisponible_jusquau' => $indisponibleJusquau->toDateString()]);
            }

            return $ticket->refresh();
        });
    }

    /** Anomalie remontée à la clôture d'une mission de ménage (P2-MEN-03). */
    public function signalerDepuisUneMission(Mission $mission, string $description, UrgenceTicket $urgence, User $auteur, ?Carbon $indisponibleJusquau = null): TicketMaintenance
    {
        $ticket = $this->signaler($mission->logement, $urgence, $description, null, null, $auteur, $indisponibleJusquau);
        $ticket->update(['origine' => OrigineDuTicket::MissionMenage, 'mission_id' => $mission->id]);

        return $ticket->refresh();
    }

    public function affecterUnTechnicien(TicketMaintenance $ticket, string $nom, ?string $contact): TicketMaintenance
    {
        $ticket->update(['technicien_nom' => trim($nom), 'technicien_contact' => $contact]);

        return $ticket->refresh();
    }

    public function imputerUnCout(TicketMaintenance $ticket, int $montant, ImputationCout $imputeA): TicketMaintenance
    {
        if ($montant < 0) {
            throw new ErreurMetier('Le montant doit être positif.', 'montant_invalide', 422);
        }

        $ticket->update(['cout_montant' => $montant, 'cout_impute_a' => $imputeA]);

        return $ticket->refresh();
    }

    /** Résolution : lève le blocage calendrier s'il y en avait un — le logement redevient réservable. */
    public function resoudre(TicketMaintenance $ticket, User $auteur): TicketMaintenance
    {
        $this->exigerPasResolu($ticket);

        return DB::transaction(function () use ($ticket, $auteur): TicketMaintenance {
            $blocage = $ticket->blocageCalendrier;
            if ($blocage instanceof BlocageCalendrier) {
                $this->calendrier->debloquer($blocage);
            }

            $ticket->update(['statut' => EtatDuTicketMaintenance::Resolu, 'resolue_par' => $auteur->id, 'resolue_le' => now()]);

            return $ticket->refresh();
        });
    }

    private function exigerPasResolu(TicketMaintenance $ticket): void
    {
        if ($ticket->statut === EtatDuTicketMaintenance::Resolu) {
            throw new ErreurMetier('Ce ticket est déjà résolu.', 'ticket_deja_resolu', 422);
        }
    }
}
