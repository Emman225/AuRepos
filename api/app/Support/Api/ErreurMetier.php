<?php

namespace App\Support\Api;

use RuntimeException;

/**
 * Refus prévu par une règle métier, que l'interface doit pouvoir RECONNAÎTRE
 * (pas seulement afficher) : elle porte un code stable, rendu dans
 * `errors.code`, en plus de son message en français.
 *
 * Exemple : `courriel_non_verifie` → le site ouvre l'écran de saisie du code.
 */
final class ErreurMetier extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $codeMetier,
        public readonly int $statut = 409,
    ) {
        parent::__construct($message);
    }

    public static function courrielNonVerifie(): self
    {
        return new self(
            'Votre adresse n’est pas encore vérifiée. Saisissez le code reçu par courriel.',
            'courriel_non_verifie',
            403,
        );
    }
}
