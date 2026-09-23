<?php

namespace App\Domain\Assistance\Enums;

/** Cycle d'une réclamation (P2-AST-01, CdC § 6.1) : ouverte → (en cours) → fermée, avec ou sans avoir. */
enum EtatDeLaReclamation: string
{
    case Ouverte = 'ouverte';
    case EnCours = 'en_cours';
    case Fermee = 'fermee';

    public function libelle(): string
    {
        return match ($this) {
            self::Ouverte => 'Ouverte',
            self::EnCours => 'En cours',
            self::Fermee => 'Fermée',
        };
    }
}
