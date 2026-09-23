<?php

namespace App\Http\Controllers\Api\V1\Restaurateur\Concerns;

use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Restaurateur;
use Illuminate\Http\Request;

/**
 * Retrouve la fiche Restaurateur du compte connecté. Comme pour le propriétaire ou
 * l'apporteur, un restaurateur est TOUJOURS créé par le back office : si la fiche manque
 * pour le compte connecté, le compte est mal configuré — 404, jamais une création silencieuse.
 */
trait ResoutLeRestaurateurConnecte
{
    private function monRestaurateur(Request $request): Restaurateur
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return Restaurateur::query()->where('user_id', $utilisateur->id)->firstOrFail();
    }
}
