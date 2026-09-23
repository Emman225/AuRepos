<?php

namespace App\Domain\Extras\Enums;

/**
 * Cycle d'une commande d'extra (P2-EXT-01) :
 *
 *   demandée → confirmée (la gestion quotidienne vérifie la demande) → fournie (le service est
 *   rendu, marqué fait).
 *
 * Sorties : annulée (par le client ou la réception), refusée (par la réception, motivée).
 * Plus léger que le circuit des commandes de repas (pas de restaurateur ni de code de
 * livraison à remettre) : un extra est exécuté en interne, jamais par un partenaire externe.
 */
enum EtatDeCommandeExtra: string
{
    case Demande = 'demande';
    case Confirmee = 'confirmee';
    case Fournie = 'fournie';
    case Annulee = 'annulee';
    case Refusee = 'refusee';

    public function libelle(): string
    {
        return match ($this) {
            self::Demande => 'Demandée',
            self::Confirmee => 'Confirmée',
            self::Fournie => 'Fournie',
            self::Annulee => 'Annulée',
            self::Refusee => 'Refusée',
        };
    }

    /** Une commande morte (annulée, refusée) n'est plus due : jamais comptée en consommation ni facturable. */
    public function estMorte(): bool
    {
        return in_array($this, [self::Annulee, self::Refusee], true);
    }
}
