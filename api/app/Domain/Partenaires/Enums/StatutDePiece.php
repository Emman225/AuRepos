<?php

namespace App\Domain\Partenaires\Enums;

enum StatutDePiece: string
{
    case EnAttente = 'en_attente';
    case Validee = 'validee';
    case Refusee = 'refusee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de vérification',
            self::Validee => 'Validée',
            self::Refusee => 'Refusée',
        };
    }
}
