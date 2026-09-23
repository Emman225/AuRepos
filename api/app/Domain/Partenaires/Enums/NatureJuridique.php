<?php

namespace App\Domain\Partenaires\Enums;

enum NatureJuridique: string
{
    case PersonnePhysique = 'personne_physique';
    case Entreprise = 'entreprise';

    public function libelle(): string
    {
        return $this === self::PersonnePhysique ? 'Personne physique' : 'Entreprise';
    }
}
