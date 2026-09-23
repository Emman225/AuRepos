<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Maintenance\Enums\PeriodiciteContrat;
use App\Domain\Maintenance\Models\ContratRecurrent;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Contrats récurrents par logement (P2-MNT-02) : un simple enregistrement de rappel
 * (nom, périodicité, prochain rappel) — pas un module de gestion de contrats complet,
 * délibérément (le CdC ne demande rien de plus, cf. rapport de tâche).
 */
final class ContratsRecurrents
{
    public function creer(Logement $logement, string $nom, PeriodiciteContrat $periodicite, Carbon $prochainRappel, ?string $notes, User $auteur): ContratRecurrent
    {
        return ContratRecurrent::create([
            'logement_id' => $logement->id, 'nom' => trim($nom), 'periodicite' => $periodicite,
            'prochain_rappel' => $prochainRappel->toDateString(), 'notes' => $notes, 'cree_par' => $auteur->id,
        ])->refresh();
    }

    /** @param array{nom?: string, periodicite?: PeriodiciteContrat, prochain_rappel?: Carbon, notes?: string|null} $donnees */
    public function modifier(ContratRecurrent $contrat, array $donnees): ContratRecurrent
    {
        if (isset($donnees['prochain_rappel']) && $donnees['prochain_rappel'] instanceof Carbon) {
            $donnees['prochain_rappel'] = $donnees['prochain_rappel']->toDateString();
        }
        if (array_key_exists('nom', $donnees)) {
            $donnees['nom'] = trim((string) $donnees['nom']);
        }

        $contrat->update($donnees);

        return $contrat->refresh();
    }

    /** Le rappel est passé : on reprogramme la prochaine échéance selon la périodicité du contrat. */
    public function reprogrammerLeProchainRappel(ContratRecurrent $contrat): ContratRecurrent
    {
        $contrat->update([
            'prochain_rappel' => $contrat->prochain_rappel->copy()->addMonths($contrat->periodicite->moisEntreDeuxEcheances())->toDateString(),
        ]);

        return $contrat->refresh();
    }

    public function desactiver(ContratRecurrent $contrat): void
    {
        $contrat->update(['actif' => false]);
    }

    /**
     * Contrats dont le rappel tombe au plus tard à cette date — pour un écran ou une tâche planifiée future.
     *
     * @return Collection<int, ContratRecurrent>
     */
    public function rappelsDus(Carbon $auPlusTard): Collection
    {
        return ContratRecurrent::query()->where('actif', true)->where('prochain_rappel', '<=', $auPlusTard->toDateString())->get();
    }
}
