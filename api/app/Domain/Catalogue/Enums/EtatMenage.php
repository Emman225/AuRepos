<?php

namespace App\Domain\Catalogue\Enums;

/**
 * État de propreté d'un logement (P2-MEN-02, CdC § 6.4) : sale → en cours → propre →
 * contrôlé. « Contrôlé » est le seul état qui vaut « logement prêt » — c'est la
 * gouvernante qui le pose, jamais l'agent de terrain lui-même (double regard).
 */
enum EtatMenage: string
{
    case Sale = 'sale';
    case EnCours = 'en_cours';
    case Propre = 'propre';
    case Controle = 'controle';

    public function libelle(): string
    {
        return match ($this) {
            self::Sale => 'Sale',
            self::EnCours => 'Ménage en cours',
            self::Propre => 'Propre',
            self::Controle => 'Contrôlé — prêt',
        };
    }
}
