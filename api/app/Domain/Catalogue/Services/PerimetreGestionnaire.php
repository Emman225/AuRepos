<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Comptes\Enums\Profil;
use App\Domain\Comptes\Models\User;

/**
 * Cloisonnement du gestionnaire à ses résidences (CdC § 9.5) : « un gestionnaire ne voit
 * que ses résidences ». Les administrateurs et le super administrateur ne sont jamais
 * restreints ; un gestionnaire sans résidence rattachée ne voit RIEN — un compte mal
 * configuré doit être corrigé par un administrateur, pas laissé tout voir par défaut.
 *
 * Champ d'application (décision du 22/09/2026) : le catalogue (résidences, logements et
 * tout ce qui s'y rattache — prix, blocages, publication, photos) et les séjours qui s'y
 * déroulent. La caisse, les propriétaires, les clients à terme et les référentiels restent
 * communs à tout le personnel d'exploitation : ils ne sont pas propres à une résidence.
 */
final class PerimetreGestionnaire
{
    /**
     * null = aucune restriction (administrateur, super administrateur).
     *
     * @return list<int>|null
     */
    public function residencesAutorisees(User $utilisateur): ?array
    {
        if ($utilisateur->profil !== Profil::Gestionnaire) {
            return null;
        }

        return $utilisateur->residences()->pluck('residences.id')->all();
    }

    public function residenceVisible(int $residenceId, User $utilisateur): bool
    {
        $autorisees = $this->residencesAutorisees($utilisateur);

        return $autorisees === null || in_array($residenceId, $autorisees, true);
    }
}
