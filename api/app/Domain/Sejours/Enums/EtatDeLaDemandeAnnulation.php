<?php

namespace App\Domain\Sejours\Enums;

/**
 * Cycle d'une demande d'annulation (P2-SEJ-06, CdC § 6.1) : le client DEMANDE sur un séjour
 * confirmé (ou déjà arrivé), un administrateur INSTRUIT — accepte (le séjour s'annule, avec
 * remboursement éventuel) ou rejette (le séjour continue, inchangé).
 */
enum EtatDeLaDemandeAnnulation: string
{
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Rejetee = 'rejetee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente d’instruction',
            self::Acceptee => 'Acceptée',
            self::Rejetee => 'Rejetée',
        };
    }
}
