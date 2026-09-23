<?php

namespace App\Domain\Sejours\Enums;

/**
 * Cycle d'un séjour (CdC § 4) :
 *   demandé → confirmé → arrivé → parti → clôturé, avec les sorties annulé et no-show.
 */
enum EtatDuSejour: string
{
    case Demande = 'demande';
    case Confirme = 'confirme';
    case Arrive = 'arrive';
    case Parti = 'parti';
    case Cloture = 'cloture';
    case Annule = 'annule';
    case NoShow = 'no_show';

    public function libelle(): string
    {
        return match ($this) {
            self::Demande => 'Demandé',
            self::Confirme => 'Confirmé',
            self::Arrive => 'Arrivé',
            self::Parti => 'Parti',
            self::Cloture => 'Clôturé',
            self::Annule => 'Annulé',
            self::NoShow => 'No-show',
        };
    }

    /** Un séjour annulé ou en no-show LIBÈRE ses dates ; tous les autres les tiennent. */
    public function occupeLeCalendrier(): bool
    {
        return ! in_array($this, [self::Annule, self::NoShow], true);
    }
}
