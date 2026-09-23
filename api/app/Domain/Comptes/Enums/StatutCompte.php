<?php

namespace App\Domain\Comptes\Enums;

enum StatutCompte: string
{
    /** Inscription reçue, pas encore confirmée (courriel non vérifié ou validation en attente). */
    case EnAttente = 'en_attente';
    case Actif = 'actif';
    /** Bloqué par un administrateur : la connexion est refusée, l'historique reste intact. */
    case Bloque = 'bloque';

    public function peutSeConnecter(): bool
    {
        return $this === self::Actif;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de vérification',
            self::Actif => 'Actif',
            self::Bloque => 'Bloqué',
        };
    }
}
