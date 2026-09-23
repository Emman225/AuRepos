<?php

namespace App\Domain\Notifications\Enums;

enum EtatNotification: string
{
    case EnAttente = 'en_attente';
    case Envoyee = 'envoyee';
    case Echouee = 'echouee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente',
            self::Envoyee => 'Envoyée',
            self::Echouee => 'Échouée',
        };
    }
}
