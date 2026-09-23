<?php

namespace App\Domain\Partenaires\Enums;

/**
 * Régime d'imposition déclaré par le bénéficiaire d'un reversement (CdC § 8.7).
 * Il décide de la retenue à la source ; il doit être justifié par une pièce.
 */
enum RegimeFiscal: string
{
    case NonRenseigne = 'non_renseigne';
    case Aucun = 'aucun';
    case Entreprenant = 'entreprenant';
    case MicroEntreprise = 'micro_entreprise';
    case ReelSimplifie = 'reel_simplifie';
    case ReelNormal = 'reel_normal';

    public function libelle(): string
    {
        return match ($this) {
            self::NonRenseigne => 'Non renseigné',
            self::Aucun => 'Aucun régime (particulier)',
            self::Entreprenant => 'Régime de l’entreprenant',
            self::MicroEntreprise => 'Micro-entreprise',
            self::ReelSimplifie => 'Réel simplifié (RSI)',
            self::ReelNormal => 'Réel normal (RNI)',
        };
    }

    /** Seuls le réel normal et le réel simplifié, JUSTIFIÉS, dispensent de retenue. */
    public function estUnRegimeReel(): bool
    {
        return in_array($this, [self::ReelSimplifie, self::ReelNormal], true);
    }
}
