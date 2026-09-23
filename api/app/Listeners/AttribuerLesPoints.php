<?php

namespace App\Listeners;

use App\Domain\Caisse\Events\ReglementEffectue;
use App\Domain\Fidelite\Services\PointsDeFidelite;

/**
 * Fin du circuit de preuve : le client gagne ses points (CdC § 4). Comme le reçu, cet écouteur
 * ne peut pas défaire la finalisation ; il s'exécute après elle.
 */
final class AttribuerLesPoints
{
    public function __construct(private readonly PointsDeFidelite $points) {}

    public function handle(ReglementEffectue $evenement): void
    {
        $this->points->acquerirPour($evenement->reglement);
    }
}
