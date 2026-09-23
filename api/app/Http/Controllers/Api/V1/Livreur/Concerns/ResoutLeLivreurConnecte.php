<?php

namespace App\Http\Controllers\Api\V1\Livreur\Concerns;

use App\Domain\Comptes\Models\User;
use App\Domain\Repas\Models\Livreur;
use Illuminate\Http\Request;

/**
 * Retrouve la fiche Livreur du compte connecté. Un livreur est TOUJOURS créé par le back
 * office : si la fiche manque pour le compte connecté, le compte est mal configuré —
 * 404, jamais une création silencieuse.
 */
trait ResoutLeLivreurConnecte
{
    private function monLivreur(Request $request): Livreur
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return Livreur::query()->where('user_id', $utilisateur->id)->firstOrFail();
    }
}
