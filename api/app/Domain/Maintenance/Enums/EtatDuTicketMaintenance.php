<?php

namespace App\Domain\Maintenance\Enums;

/** Cycle d'un ticket de maintenance (P2-MNT-01) : ouvert → résolu, sans étape intermédiaire non écrite. */
enum EtatDuTicketMaintenance: string
{
    case Ouvert = 'ouvert';
    case Resolu = 'resolu';

    public function libelle(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert',
            self::Resolu => 'Résolu',
        };
    }
}
