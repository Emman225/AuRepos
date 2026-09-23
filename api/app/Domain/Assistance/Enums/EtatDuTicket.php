<?php

namespace App\Domain\Assistance\Enums;

/** Cycle d'un ticket d'assistance (P2-AST-01, CdC § 6.1) : ouvert → en cours → fermé. */
enum EtatDuTicket: string
{
    case Ouvert = 'ouvert';
    case EnCours = 'en_cours';
    case Ferme = 'ferme';

    public function libelle(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert',
            self::EnCours => 'En cours',
            self::Ferme => 'Fermé',
        };
    }
}
