<?php

namespace App\Domain\Catalogue\Enums;

/**
 * Bouton « Occupée / Disponible » du propriétaire (CdC § 6.2). Une résidence
 * occupée disparaît de la recherche publique ; les séjours confirmés restent honorés.
 */
enum Disponibilite: string
{
    case Disponible = 'disponible';
    case Occupee = 'occupee';
}
