<?php

namespace App\Domain\Sejours\Enums;

/** Un état des lieux d'entrée, un de sortie — au plus un de chaque par séjour (P2-SEJ-02). */
enum TypeEtatDesLieux: string
{
    case Entree = 'entree';
    case Sortie = 'sortie';

    public function libelle(): string
    {
        return match ($this) {
            self::Entree => 'État des lieux d’entrée',
            self::Sortie => 'État des lieux de sortie',
        };
    }
}
