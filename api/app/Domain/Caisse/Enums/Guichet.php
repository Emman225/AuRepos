<?php

namespace App\Domain\Caisse\Enums;

/** Les guichets du cahier des charges (§ 8.1). Ceux du lot 1 d'abord ; les autres viennent avec leurs lots. */
enum Guichet: string
{
    case Sejours = 'sejours';
    case CreancesATerme = 'creances_a_terme';
    case EnLigne = 'en_ligne';
    case Avances = 'avances';
    case DettesPartenaires = 'dettes_partenaires';
    // Remboursement client sur une demande d'annulation instruite (P2-SEJ-06) : jamais une dette partenaire.
    case Remboursements = 'remboursements';

    public function libelle(): string
    {
        return match ($this) {
            self::Sejours => 'Séjours (encaissements agence)',
            self::CreancesATerme => 'Créances à terme',
            self::EnLigne => 'Paiement en ligne',
            self::Avances => 'Avances clients',
            self::DettesPartenaires => 'Dettes partenaires',
            self::Remboursements => 'Remboursements clients',
        };
    }

    /**
     * Préfixe du reçu (CdC § 8.4). Le numéro qui suit est commun à toute la caisse.
     * RL (en ligne), RK et RK-R (cautions) viendront avec le paiement en ligne et le guichet des cautions.
     */
    public function prefixeDuRecu(): ?string
    {
        return match ($this) {
            self::Sejours => 'RC',
            self::CreancesATerme => 'RC-CT',
            self::EnLigne => 'RL',
            self::Avances => 'RA',
            self::DettesPartenaires => null,
            self::Remboursements => null,
        };
    }
}
