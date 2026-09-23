<?php

namespace App\Listeners;

use App\Domain\Caisse\Events\ReglementEffectue;
use App\Domain\Caisse\Services\Recus;

/**
 * Fin du circuit de preuve : le reçu PDF est conservé, puis envoyé UNE fois au client (CdC § 8.4).
 * Rien ici ne peut défaire la finalisation : `Recus` avale et journalise ses propres pannes.
 */
final class EnvoyerLeRecu
{
    public function __construct(private readonly Recus $recus) {}

    public function handle(ReglementEffectue $evenement): void
    {
        $this->recus->envoyerUneFois($evenement->reglement);
    }
}
