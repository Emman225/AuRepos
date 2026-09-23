<?php

namespace App\Domain\Fiscalite\Enums;

enum StatutDeTransmissionFne: string
{
    case ATransmettre = 'a_transmettre';
    case Transmise = 'transmise';
    case Refusee = 'refusee';
    /** `fne.enabled` est à false (identifiants DGI absents) : aucune tentative n'a eu lieu. */
    case NonConfiguree = 'non_configuree';

    public function libelle(): string
    {
        return match ($this) {
            self::ATransmettre => 'À transmettre',
            self::Transmise => 'Transmise',
            self::Refusee => 'Refusée par la DGI',
            self::NonConfiguree => 'Transmission non configurée',
        };
    }
}
