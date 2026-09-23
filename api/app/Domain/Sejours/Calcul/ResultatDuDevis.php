<?php

namespace App\Domain\Sejours\Calcul;

use App\Domain\Tarification\Calcul\DevisDeSejour;
use App\Domain\Tarification\Models\CodePromo;

/**
 * Le devis complet d'un séjour et ce qui l'a produit — partagé par la réservation ET
 * le devis autonome (CdC § 5.1, § 7.3) : même moteur, même prix négocié, même code promo.
 */
final readonly class ResultatDuDevis
{
    /** @param  array{solde: int, utilisables: int, valeur: int, valeur_du_point: int, plafonne: bool}  $points */
    public function __construct(
        public DevisDeSejour $devis,
        public array $points,
        public ?CodePromo $codePromo,
        public ?int $tarifNegocie,
    ) {}
}
