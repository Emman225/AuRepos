<?php

namespace App\Domain\Catalogue\Enums;

/** Cycle de publication d'un logement (CdC § 7.1). Seul « publié » est visible du public. */
enum EtatPublication: string
{
    case Brouillon = 'brouillon';
    case EnAttente = 'en_attente';
    case Refuse = 'refuse';
    case PrixANegocier = 'prix_a_negocier';
    case Publie = 'publie';
    case Suspendu = 'suspendu';

    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnAttente => 'En attente de validation',
            self::Refuse => 'Refusé',
            self::PrixANegocier => 'Prix à négocier',
            self::Publie => 'Publié',
            self::Suspendu => 'Suspendu',
        };
    }

    public function visibleDuPublic(): bool
    {
        return $this === self::Publie;
    }
}
