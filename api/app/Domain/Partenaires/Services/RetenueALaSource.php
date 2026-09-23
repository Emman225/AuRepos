<?php

namespace App\Domain\Partenaires\Services;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Partenaires\Enums\NatureJuridique;

/**
 * Taux de retenue à la source applicable à un reversement (CdC § 8.7).
 *
 *   personne physique                          → 7,5 %
 *   entreprise hors régime réel, ou non renseigné → 2 %
 *   entreprise au réel normal ou simplifié, JUSTIFIÉ → aucune retenue
 *   compte « propriétaire interne »            → aucune retenue
 *
 * Un régime réel déclaré sans pièce validée ne dispense de rien : c'est la
 * retenue la plus élevée applicable à la nature du bénéficiaire qui joue.
 * Le taux rendu ici vaut pour AUJOURD'HUI ; il sera figé sur chaque
 * reversement à sa date (P3-PRO-05).
 */
final class RetenueALaSource
{
    public function __construct(private readonly Parametres $parametres) {}

    /** @return array{taux: float, motif: string} */
    public function pour(Proprietaire $proprietaire): array
    {
        if ($proprietaire->interne) {
            return ['taux' => 0.0, 'motif' => 'Compte propriétaire interne : reversement interne, sans retenue.'];
        }

        if ($proprietaire->nature === NatureJuridique::PersonnePhysique) {
            return [
                'taux' => (float) $this->parametres->valeur('taxes.retenue_personne_physique'),
                'motif' => 'Personne physique.',
            ];
        }

        if ($proprietaire->regimeReelJustifie()) {
            return ['taux' => 0.0, 'motif' => 'Entreprise au '.mb_strtolower($proprietaire->regime_fiscal->libelle()).', justifié.'];
        }

        return [
            'taux' => (float) $this->parametres->valeur('taxes.retenue_entreprise_hors_reel'),
            'motif' => $proprietaire->regime_fiscal->estUnRegimeReel()
                ? 'Régime réel déclaré mais non justifié par une pièce validée.'
                : 'Entreprise hors régime réel ('.mb_strtolower($proprietaire->regime_fiscal->libelle()).').',
        ];
    }
}
