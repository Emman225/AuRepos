<?php

namespace App\Domain\Comptes\Enums;

enum UsageDuCode: string
{
    case VerificationCourriel = 'verification_courriel';
    case ReinitialisationMotDePasse = 'reinitialisation_mot_de_passe';

    public function objetDuCourriel(): string
    {
        return match ($this) {
            self::VerificationCourriel => 'Votre code de vérification',
            self::ReinitialisationMotDePasse => 'Votre code pour changer de mot de passe',
        };
    }

    public function phrase(): string
    {
        return match ($this) {
            self::VerificationCourriel => 'Pour terminer votre inscription, saisissez ce code :',
            self::ReinitialisationMotDePasse => 'Pour choisir un nouveau mot de passe, saisissez ce code :',
        };
    }
}
