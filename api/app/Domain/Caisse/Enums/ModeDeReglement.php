<?php

namespace App\Domain\Caisse\Enums;

/** Le mode RÉEL est celui déclaré à la DGI sur la facture (CdC § 8.3). */
enum ModeDeReglement: string
{
    case Especes = 'especes';
    case MobileMoney = 'mobile_money';
    case Carte = 'carte';
    case Virement = 'virement';
    case Cheque = 'cheque';
    case Avance = 'avance';
    case CanalExterne = 'canal_externe';

    public function libelle(): string
    {
        return match ($this) {
            self::Especes => 'Espèces',
            self::MobileMoney => 'Mobile money',
            self::Carte => 'Carte bancaire',
            self::Virement => 'Virement',
            self::Cheque => 'Chèque',
            self::Avance => 'Avance client',
            self::CanalExterne => 'Encaissé par le canal',
        };
    }

    /** Clé du paramètre « Comptant / Agence » qui l'active, s'il y en a un. */
    public function parametre(): ?string
    {
        return match ($this) {
            self::Especes, self::MobileMoney, self::Carte, self::Virement, self::Cheque => 'comptant.'.$this->value,
            default => null,
        };
    }
}
