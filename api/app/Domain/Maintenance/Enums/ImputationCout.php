<?php

namespace App\Domain\Maintenance\Enums;

/** À qui revient le coût d'une intervention de maintenance (P2-MNT-01). */
enum ImputationCout: string
{
    case Proprietaire = 'proprietaire';
    case Entreprise = 'entreprise';

    public function libelle(): string
    {
        return match ($this) {
            self::Proprietaire => 'Propriétaire',
            self::Entreprise => 'Entreprise',
        };
    }
}
