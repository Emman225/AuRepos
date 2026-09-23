<?php

namespace App\Domain\Maintenance\Enums;

/** D'où vient un ticket de maintenance (P2-MNT-01) : une anomalie remontée en clôture de mission de ménage, ou un signalement direct. */
enum OrigineDuTicket: string
{
    case MissionMenage = 'mission_menage';
    case Direct = 'direct';

    public function libelle(): string
    {
        return match ($this) {
            self::MissionMenage => 'Anomalie relevée en ménage',
            self::Direct => 'Signalement direct',
        };
    }
}
