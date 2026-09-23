<?php

namespace App\Http\Controllers\Api\V1\Apporteur\Concerns;

use App\Domain\Comptes\Models\User;
use App\Domain\Partenaires\Models\Apporteur;
use Illuminate\Http\Request;

/**
 * Retrouve la fiche Apporteur du compte connecté. Comme pour le propriétaire, un apporteur
 * est TOUJOURS créé par le back office : si la fiche manque pour le compte connecté, le
 * compte est mal configuré — 404, jamais une création silencieuse.
 */
trait ResoutLApporteurConnecte
{
    private function monApporteur(Request $request): Apporteur
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return Apporteur::query()->where('user_id', $utilisateur->id)->firstOrFail();
    }
}
