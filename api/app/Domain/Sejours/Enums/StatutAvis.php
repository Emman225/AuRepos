<?php

namespace App\Domain\Sejours\Enums;

/** Avis vérifié de fin de séjour (CdC § 5.1, P2-AVI-01) : modéré avant toute publication publique. */
enum StatutAvis: string
{
    case EnAttente = 'en_attente';
    case Publie = 'publie';
    case Refuse = 'refuse';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de modération',
            self::Publie => 'Publié',
            self::Refuse => 'Refusé',
        };
    }
}
