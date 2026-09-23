<?php

namespace App\Domain\Partenaires\Enums;

/** Choisi sur le mandat (CdC § 7.2). Dans les deux cas, le reversement porte sur les nuitées CONSOMMÉES. */
enum ModeDeRemuneration: string
{
    /** Le propriétaire reçoit son prix négocié × nuitées ; la marge est prix de vente − prix négocié. */
    case PrixNegocie = 'prix_negocie';
    /** L'entreprise fixe le prix ; le propriétaire reçoit le prix HT × (1 − commission) × nuitées. */
    case Commission = 'commission';

    public function libelle(): string
    {
        return $this === self::PrixNegocie ? 'Prix négocié + prix de vente administrateur' : 'Commission sur le prix de vente';
    }
}
