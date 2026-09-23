<?php

namespace App\Domain\Maintenance\Enums;

/** Périodicité d'un contrat récurrent (P2-MNT-02) : sert à reprogrammer le prochain rappel. */
enum PeriodiciteContrat: string
{
    case Mensuelle = 'mensuelle';
    case Trimestrielle = 'trimestrielle';
    case Semestrielle = 'semestrielle';
    case Annuelle = 'annuelle';

    public function libelle(): string
    {
        return match ($this) {
            self::Mensuelle => 'Mensuelle',
            self::Trimestrielle => 'Trimestrielle',
            self::Semestrielle => 'Semestrielle',
            self::Annuelle => 'Annuelle',
        };
    }

    /** Nombre de mois entre deux échéances — sert à calculer le prochain rappel. */
    public function moisEntreDeuxEcheances(): int
    {
        return match ($this) {
            self::Mensuelle => 1,
            self::Trimestrielle => 3,
            self::Semestrielle => 6,
            self::Annuelle => 12,
        };
    }
}
