<?php

namespace App\Domain\Repas\Enums;

/** Catégorie d'un produit de la carte d'un restaurateur (CdC — « Plats et boissons par restaurateur »). */
enum CategorieProduit: string
{
    case Plat = 'plat';
    case Boisson = 'boisson';

    public function libelle(): string
    {
        return match ($this) {
            self::Plat => 'Plat',
            self::Boisson => 'Boisson',
        };
    }
}
