<?php

namespace App\Domain\Transferts\Enums;

/**
 * Cycle d'un transfert (CdC § 6.6, cas « pendant le séjour ») :
 *   demandé par le client → affecté (chauffeur, véhicule, code de prise en charge émis)
 *   → terminé (clôturé par le chauffeur au moyen du code), avec la sortie annulé.
 */
enum EtatDuTransfert: string
{
    case Demande = 'demande';
    case Affecte = 'affecte';
    case Termine = 'termine';
    case Annule = 'annule';

    public function libelle(): string
    {
        return match ($this) {
            self::Demande => 'Demandé',
            self::Affecte => 'Affecté',
            self::Termine => 'Terminé',
            self::Annule => 'Annulé',
        };
    }
}
