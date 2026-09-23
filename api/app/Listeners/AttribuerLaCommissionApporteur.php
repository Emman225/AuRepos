<?php

namespace App\Listeners;

use App\Domain\Caisse\Events\ReglementEffectue;
use App\Domain\Partenaires\Services\Parrainage;

/**
 * Fin du circuit de preuve : si le règlement encaisse une tranche du séjour d'un client
 * parrainé, son apporteur gagne sa commission sur CETTE tranche (P3-APP-01). Comme le
 * reçu et les points, cet écouteur ne peut plus défaire la finalisation ; il s'exécute après elle.
 */
final class AttribuerLaCommissionApporteur
{
    public function __construct(private readonly Parrainage $parrainage) {}

    public function handle(ReglementEffectue $evenement): void
    {
        $this->parrainage->attribuerLaCommission($evenement->reglement);
    }
}
