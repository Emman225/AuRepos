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
    // Dépôt et restitution de caution (P2-CAU-01) : des règlements comme les autres, rattachés
    // à UN séjour par `reglements.sejour_id` — jamais par imputation (la caution n'entre pas
    // dans le reste dû du séjour, cf. `SoldeDesSejours`).
    case Cautions = 'cautions';
    // Guichets d'encaissement des consommations demandées PENDANT le séjour (P2-TRF-03) :
    // jamais une ligne du net à payer figé du séjour — imputés sur la consommation elle-même
    // (Transfert, CommandeExtra), voir App\Domain\Caisse\Services\Caisse::encaisserUneConsommation.
    case Extras = 'extras';
    case Transferts = 'transferts';

    public function libelle(): string
    {
        return match ($this) {
            self::Sejours => 'Séjours (encaissements agence)',
            self::CreancesATerme => 'Créances à terme',
            self::EnLigne => 'Paiement en ligne',
            self::Avances => 'Avances clients',
            self::DettesPartenaires => 'Dettes partenaires',
            self::Remboursements => 'Remboursements clients',
            self::Cautions => 'Cautions',
            self::Extras => 'Extras',
            self::Transferts => 'Transferts',
        };
    }

    /**
     * Préfixe du reçu d'un ENCAISSEMENT (CdC § 8.4). Le numéro qui suit est commun à toute la caisse.
     * RL (en ligne) viendra avec le paiement en ligne.
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
            self::Cautions => 'RK',
            self::Extras => 'RC-EX',
            self::Transferts => 'RC-TR',
        };
    }

    /**
     * Préfixe du reçu d'un DÉCAISSEMENT (P2-CAU-01) : seule la restitution de caution en émet un.
     * Un décaissement ordinaire (dette partenaire, remboursement) n'a jamais eu de reçu.
     */
    public function prefixeDuRecuDecaissement(): ?string
    {
        return $this === self::Cautions ? 'RK-R' : null;
    }
}
