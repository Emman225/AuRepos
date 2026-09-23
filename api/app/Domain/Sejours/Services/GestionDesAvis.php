<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\StatutAvis;
use App\Domain\Sejours\Models\Avis;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;

/**
 * Avis vérifiés de fin de séjour (CdC § 5.1, P2-AVI-01) : un client note un séjour CLÔTURÉ,
 * jamais avant, jamais plus d'une fois. Rien n'est public tant qu'un administrateur ne l'a
 * pas modéré — publié ou refusé, avec motif dans ce second cas.
 */
final class GestionDesAvis
{
    public function soumettre(Sejour $sejour, int $note, ?string $commentaire): Avis
    {
        if ($sejour->etat !== EtatDuSejour::Cloture) {
            throw new ErreurMetier('Un avis ne peut être déposé qu’après la clôture du séjour.', 'sejour_non_termine', 422);
        }
        if ($sejour->avis()->exists()) {
            throw new ErreurMetier('Un avis a déjà été déposé pour ce séjour.', 'avis_deja_soumis', 422);
        }

        return Avis::create(['sejour_id' => $sejour->id, 'note' => $note, 'commentaire' => $commentaire]);
    }

    public function publier(Avis $avis, User $administrateur): Avis
    {
        $avis->update(['statut' => StatutAvis::Publie, 'modere_par' => $administrateur->id, 'modere_le' => now(), 'motif_refus' => null]);

        return $avis->refresh();
    }

    public function refuser(Avis $avis, string $motif, User $administrateur): Avis
    {
        $avis->update(['statut' => StatutAvis::Refuse, 'modere_par' => $administrateur->id, 'modere_le' => now(), 'motif_refus' => $motif]);

        return $avis->refresh();
    }
}
