<?php

namespace App\Domain\Notifications\Contracts;

use App\Support\Api\ErreurMetier;

/**
 * Passerelle d'envoi pour UN canal texte (SMS ou WhatsApp). Même contrat pour les deux :
 * un numéro de téléphone, un corps de message, un succès ou une raison d'échec en clair.
 */
interface PasserelleDeMessage
{
    public function nom(): string;

    public function estConfiguree(): bool;

    /** @throws ErreurMetier si l'envoi échoue ; le message porte la raison, en clair. */
    public function envoyer(string $destinataire, string $corps): void;
}
