<?php

namespace App\Domain\Catalogue\Enums;

/** Le pourcentage retenu de chaque politique se règle dans Paramètres › Séjours. */
enum PolitiqueAnnulation: string
{
    case Flexible = 'flexible';
    case Moderee = 'moderee';
    case Stricte = 'stricte';

    public function libelle(): string
    {
        return match ($this) {
            self::Flexible => 'Flexible',
            self::Moderee => 'Modérée',
            self::Stricte => 'Stricte',
        };
    }
}
