<?php

namespace App\Domain\Repas\Enums;

/**
 * Cycle d'une commande de repas (CdC — « Repas et boissons ») :
 *
 *   demandée → confirmée (règlement vérifié) → en préparation → prête (bon de préparation)
 *   → en livraison (livreur affecté, code émis) → livrée (code saisi par le livreur).
 *
 * Sorties : annulée (par le client ou la réception), refusée (par la réception ou le
 * restaurateur, motivée).
 */
enum EtatDeCommande: string
{
    case Demande = 'demande';
    case Confirmee = 'confirmee';
    case EnPreparation = 'en_preparation';
    case Prete = 'prete';
    case EnLivraison = 'en_livraison';
    case Livree = 'livree';
    case Annulee = 'annulee';
    case Refusee = 'refusee';

    public function libelle(): string
    {
        return match ($this) {
            self::Demande => 'Demandée',
            self::Confirmee => 'Confirmée',
            self::EnPreparation => 'En préparation',
            self::Prete => 'Prête',
            self::EnLivraison => 'En livraison',
            self::Livree => 'Livrée',
            self::Annulee => 'Annulée',
            self::Refusee => 'Refusée',
        };
    }
}
