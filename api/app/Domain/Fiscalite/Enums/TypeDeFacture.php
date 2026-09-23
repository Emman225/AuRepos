<?php

namespace App\Domain\Fiscalite\Enums;

enum TypeDeFacture: string
{
    /** Réservation réglée en agence ou à terme (CdC § 9.4) : aucune valeur fiscale tant qu'aucune facture n'a suivi. */
    case Proforma = 'proforma';
    case Facture = 'facture';
    /** Montant négatif, certifiée par la DGI, annule tout ou partie d'une facture (CdC § 14). */
    case Avoir = 'avoir';
    /**
     * Retenue sur caution (P2-CAU-02) : facture normalisée « frais de dégradation / retard ».
     * Distincte de `Facture` à dessein — elle ne heurte jamais « un séjour, une facture » (CdC § 9.4),
     * qui ne vaut que pour la facture DU SÉJOUR.
     */
    case FraisCaution = 'frais_caution';

    public function libelle(): string
    {
        return match ($this) {
            self::Proforma => 'Proforma',
            self::Facture => 'Facture',
            self::Avoir => 'Avoir',
            self::FraisCaution => 'Frais de dégradation / retard',
        };
    }
}
