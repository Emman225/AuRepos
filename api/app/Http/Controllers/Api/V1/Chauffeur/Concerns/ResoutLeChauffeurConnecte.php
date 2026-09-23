<?php

namespace App\Http\Controllers\Api\V1\Chauffeur\Concerns;

use App\Domain\Comptes\Models\User;
use App\Domain\Transferts\Models\Chauffeur;
use Illuminate\Http\Request;

/**
 * Retrouve la fiche Chauffeur du compte connecté. Comme pour l'apporteur et le propriétaire,
 * un chauffeur est TOUJOURS créé par le back office : si la fiche manque pour le compte
 * connecté, le compte est mal configuré — 404, jamais une création silencieuse.
 */
trait ResoutLeChauffeurConnecte
{
    private function monChauffeur(Request $request): Chauffeur
    {
        /** @var User $utilisateur */
        $utilisateur = $request->user();

        return Chauffeur::query()->where('user_id', $utilisateur->id)->firstOrFail();
    }
}
