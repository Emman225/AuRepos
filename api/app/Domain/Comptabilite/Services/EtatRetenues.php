<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Partenaires\Services\RetenueALaSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * État des retenues à la source, par mois et par bénéficiaire, pour la déclaration DGI
 * (CdC § 8.7, § 9.3, P3-CPT-02).
 *
 * Limite assumée et à corriger dès que P3-PRO-04/05 seront livrés : il n'existe pas encore
 * de ledger de reversement propriétaire distinguant brut / retenue / net, ni de taux figé à
 * la date du reversement. Le seul montant déjà enregistré qui s'en approche est le
 * DÉCAISSEMENT effectué au guichet « dettes partenaires » vers le compte d'un propriétaire —
 * on le prend ici comme montant brut reversé, et on lui applique le taux ACTUEL de
 * `RetenueALaSource::pour()` (pas un taux historique figé, puisqu'aucun n'est encore
 * enregistré). Une fois P3-PRO-05 livré, cette méthode devra lire le taux figé sur chaque
 * reversement plutôt que le recalculer.
 */
final class EtatRetenues
{
    public function __construct(private readonly RetenueALaSource $retenueALaSource) {}

    public function parBeneficiaireEtMois(Carbon $du, Carbon $au): array
    {
        $decaissements = Reglement::query()->with('tiers')
            ->where('sens', 'decaissement')
            ->where('guichet', Guichet::DettesPartenaires)
            ->where('etat', EtatDuReglement::Effectue)
            ->whereBetween('saisi_le', [$du, $au])
            ->get();

        $lignes = collect();
        foreach ($decaissements as $decaissement) {
            $proprietaire = Proprietaire::query()->where('user_id', $decaissement->tiers_id)->first();
            if ($proprietaire === null) {
                continue; // Décaissement vers un tiers qui n'est pas un propriétaire : hors champ de cet état.
            }

            ['taux' => $taux, 'motif' => $motif] = $this->retenueALaSource->pour($proprietaire);
            $brut = $decaissement->montant;
            $retenue = (int) round($brut * $taux / 100);

            $lignes->push([
                'beneficiaire' => $proprietaire->nomAffiche(),
                'mois' => $decaissement->saisi_le->format('Y-m'),
                'regime' => $proprietaire->interne ? 'Compte interne' : $proprietaire->nature->value,
                'taux_pourcent' => $taux,
                'motif_taux' => $motif,
                'montant_brut' => $brut,
                'retenue' => $retenue,
                'net_verse' => $brut - $retenue,
            ]);
        }

        $parMois = $lignes->groupBy('mois')->map(fn (Collection $g, string $mois): array => [
            'mois' => $mois, 'montant_brut' => (int) $g->sum('montant_brut'), 'retenue' => (int) $g->sum('retenue'), 'net_verse' => (int) $g->sum('net_verse'),
        ])->values();

        return [
            'lignes' => $lignes->values()->all(),
            'par_mois' => $parMois->all(),
            'totaux' => [
                'montant_brut' => (int) $lignes->sum('montant_brut'),
                'retenue' => (int) $lignes->sum('retenue'),
                'net_verse' => (int) $lignes->sum('net_verse'),
            ],
        ];
    }
}
