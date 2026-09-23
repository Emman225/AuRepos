<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Models\Parametre;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Validation\Models\ChangementAValider;
use App\Domain\Validation\Services\DoubleValidation;
use App\Support\Api\ErreurMetier;
use Closure;
use Illuminate\Support\Collection;

/**
 * Pourcentage entreprise (CdC § 7.3) : « entre en vigueur après validation par un second
 * administrateur ; dérogation par logement possible, même double validation, bandeau
 * permanent listant les dérogations. »
 *
 * Deux sujets, un seul mécanisme (`DoubleValidation`, déjà utilisé pour le prix de vente) :
 *   - le taux global, porté par la ligne `Parametre` de `proprietaires.pourcentage_entreprise` ;
 *   - une dérogation par logement, sur `logements.pourcentage_entreprise_derogation`
 *     (null = pas de dérogation, le taux global s'applique).
 */
final class PourcentageEntreprise
{
    private const CLE_TAUX_GLOBAL = 'proprietaires.pourcentage_entreprise';

    private const CHAMP_DEROGATION = 'pourcentage_entreprise_derogation';

    public function __construct(
        private readonly DoubleValidation $doubleValidation,
        private readonly Parametres $parametres,
    ) {}

    public function proposerLeTauxGlobal(float $taux, ?string $motif, User $administrateur): ChangementAValider
    {
        return $this->doubleValidation->proposer(
            $this->parametres->parametreModele(self::CLE_TAUX_GLOBAL), 'valeur', $taux, $administrateur, $motif, $this->controle(),
        );
    }

    /** $taux à null retire la dérogation : le logement revient au taux global. */
    public function proposerUneDerogation(Logement $logement, ?float $taux, ?string $motif, User $administrateur): ChangementAValider
    {
        return $this->doubleValidation->proposer($logement, self::CHAMP_DEROGATION, $taux, $administrateur, $motif, $this->controle());
    }

    /** Appelé après validation d'un changement sur le taux global : le cache des Paramètres doit s'oublier. */
    public function apresValidation(ChangementAValider $changement): void
    {
        if ($changement->sujet instanceof Parametre && $changement->sujet->cle === self::CLE_TAUX_GLOBAL) {
            $this->parametres->oublier();
        }
    }

    /** Le taux qui s'applique réellement à ce logement : sa dérogation, sinon le taux global. */
    public function tauxApplicable(Logement $logement): float
    {
        return $logement->pourcentage_entreprise_derogation ?? (float) $this->parametres->valeur(self::CLE_TAUX_GLOBAL);
    }

    /**
     * Bandeau permanent listant les dérogations en vigueur (CdC § 7.3).
     *
     * @return Collection<int, array{logement_id: int, reference: string, nom: string, residence: string, taux_global: float, taux_derogation: float|null}>
     */
    public function derogations(): Collection
    {
        $tauxGlobal = (float) $this->parametres->valeur(self::CLE_TAUX_GLOBAL);

        return Logement::query()
            ->whereNotNull(self::CHAMP_DEROGATION)
            ->with(['residence'])
            ->get()
            ->map(fn (Logement $l): array => [
                'logement_id' => $l->id, 'reference' => $l->reference, 'nom' => $l->nom,
                'residence' => $l->residence->nom,
                'taux_global' => $tauxGlobal, 'taux_derogation' => $l->pourcentage_entreprise_derogation,
            ])
            ->values();
    }

    /** @return Closure(mixed, mixed): void */
    private function controle(): Closure
    {
        return function (mixed $actuel, mixed $propose): void {
            if ($propose !== null && ((float) $propose < 0 || (float) $propose > 500)) {
                throw new ErreurMetier('Le pourcentage entreprise doit être compris entre 0 et 500 %.', 'pourcentage_hors_limites', 422);
            }
        };
    }
}
