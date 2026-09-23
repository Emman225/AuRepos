<?php

namespace App\Domain\Sejours\Enums;

/** Dossier de demande de compte à crédit (CdC § 5.1, 5.3). */
enum StatutDemandeATerme: string
{
    case Aucune = 'aucune';
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';

    public function libelle(): string
    {
        return match ($this) {
            self::Aucune => 'Aucune demande',
            self::EnAttente => 'Demande en attente',
            self::Acceptee => 'Compte à crédit actif',
            self::Refusee => 'Demande refusée',
        };
    }
}
