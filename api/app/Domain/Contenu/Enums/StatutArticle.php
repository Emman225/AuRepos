<?php

namespace App\Domain\Contenu\Enums;

enum StatutArticle: string
{
    case Brouillon = 'brouillon';
    case Publie = 'publie';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::Publie => 'Publié',
        };
    }
}
