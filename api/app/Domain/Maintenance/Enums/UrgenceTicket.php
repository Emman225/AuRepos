<?php

namespace App\Domain\Maintenance\Enums;

/** Urgence d'un ticket de maintenance (P2-MNT-01). Seul « bloquante » retire le logement du calendrier. */
enum UrgenceTicket: string
{
    case Basse = 'basse';
    case Normale = 'normale';
    case Haute = 'haute';
    case Bloquante = 'bloquante';

    public function libelle(): string
    {
        return match ($this) {
            self::Basse => 'Basse',
            self::Normale => 'Normale',
            self::Haute => 'Haute',
            self::Bloquante => 'Bloquante',
        };
    }
}
