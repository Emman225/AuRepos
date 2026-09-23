<?php

namespace App\Domain\Partenaires\Enums;

enum TypeDePiece: string
{
    case PieceIdentite = 'piece_identite';
    case TitrePropriete = 'titre_propriete';
    case Bail = 'bail';
    case Rib = 'rib';
    case Mandat = 'mandat';
    case Dfe = 'dfe';
    case AttestationRegime = 'attestation_regime';
    case Rccm = 'rccm';
    case Bilan = 'bilan';
    // Ligne d'état des lieux d'entrée ou de sortie (P2-SEJ-02) : même mécanisme chiffré, nouveau type seulement.
    case PhotoEtatDesLieux = 'photo_etat_des_lieux';
    // Clôture d'une mission de ménage (P2-MEN-03) : même mécanisme chiffré, nouveau type seulement.
    case PhotoMission = 'photo_mission';
    // Justificatif d'une retenue sur caution (P2-CAU-01/02) : photo du dégât, facture de réparation… — même mécanisme chiffré.
    case JustificatifCaution = 'justificatif_caution';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::PieceIdentite => 'Pièce d’identité',
            self::TitrePropriete => 'Titre de propriété',
            self::Bail => 'Bail',
            self::Rib => 'RIB',
            self::Mandat => 'Mandat de gestion signé',
            self::Dfe => 'Déclaration fiscale d’existence (DFE)',
            self::AttestationRegime => 'Attestation de régime d’imposition',
            self::Rccm => 'RCCM',
            self::Bilan => 'Bilan financier',
            self::PhotoEtatDesLieux => 'Photo d’état des lieux',
            self::PhotoMission => 'Photo de clôture de mission',
            self::JustificatifCaution => 'Justificatif de retenue sur caution',
            self::Autre => 'Autre document',
        };
    }

    /** Pièces qui prouvent le régime fiscal déclaré. */
    public function justifieLeRegime(): bool
    {
        return in_array($this, [self::Dfe, self::AttestationRegime], true);
    }
}
