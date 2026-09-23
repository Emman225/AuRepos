<?php

namespace App\Domain\Caisse\Events;

use App\Domain\Caisse\Models\Reglement;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un règlement vient d'aller au bout de son circuit : la somme compte désormais.
 * S'y abonneront le reçu PDF (P1-CAI-07), la confirmation du séjour (P1-RES-09),
 * les points de fidélité (P1-CAI-06) et la commission de l'apporteur (P3-APP-01).
 */
final class ReglementEffectue
{
    use Dispatchable;

    public function __construct(public readonly Reglement $reglement) {}
}
