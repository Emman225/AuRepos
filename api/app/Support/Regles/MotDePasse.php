<?php

namespace App\Support\Regles;

use Illuminate\Validation\Rules\Password;

/** Règle de mot de passe, la même à l'inscription, à la réinitialisation et au changement. */
final class MotDePasse
{
    public static function regle(): Password
    {
        return Password::min(8)->letters()->numbers();
    }
}
