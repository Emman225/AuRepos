<?php

namespace App\Http\Controllers\Api\V1\Proprietaire\Concerns;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Comptes\Models\User;
use Illuminate\Http\Request;

/**
 * Retrouve la fiche Proprietaire du compte connecté. Contrairement à l'espace client
 * (Client::de(), qui crée la fiche à la volée), un propriétaire est TOUJOURS créé par
 * le back office (CdC § 7.2) : si la fiche manque pour le compte connecté, le compte
 * est mal configuré — 404, jamais une création silencieuse.
 */
trait ResoutLeProprietaireConnecte
{
    private function monProprietaire(Request $request): Proprietaire
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return Proprietaire::query()->where('user_id', $utilisateur->id)->firstOrFail();
    }
}
