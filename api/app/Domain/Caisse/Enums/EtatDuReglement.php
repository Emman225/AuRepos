<?php

namespace App\Domain\Caisse\Enums;

enum EtatDuReglement: string
{
    case EnAttente = 'en_attente';
    case APayer = 'a_payer';
    case PreuveJointe = 'preuve_jointe';
    case Effectue = 'effectue';
    case Rejete = 'rejete';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de validation',
            self::APayer => 'À payer',
            self::PreuveJointe => 'À payer, preuve jointe',
            self::Effectue => 'Effectué',
            self::Rejete => 'Rejeté',
        };
    }

    /** Saisi mais pas encore effectué : la somme RÉSERVE SA PLACE sur le reste dû (CdC § 8.2). */
    public function enCours(): bool
    {
        return in_array($this, [self::EnAttente, self::APayer, self::PreuveJointe], true);
    }
}
