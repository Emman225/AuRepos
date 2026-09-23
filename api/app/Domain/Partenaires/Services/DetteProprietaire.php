<?php

namespace App\Domain\Partenaires\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Partenaires\Enums\ModeDeRemuneration;
use App\Domain\Partenaires\Enums\RegimeFiscal;
use App\Domain\Partenaires\Models\ChargeProprietaire;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\BonDeMiseADisposition;
use App\Support\Api\ErreurMetier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ce que l'entreprise doit à un propriétaire (CdC § 7.2 et § 8.7) :
 *
 *   brut = Σ, sur les bons VALIDÉS dont le séjour est réellement consommé (parti ou clôturé) :
 *       - mode « prix négocié » : prix propriétaire par nuit (figé sur le bon) × nuitées ;
 *       - mode « commission »   : hébergement net HT du séjour (figé sur son devis) × (1 − commission).
 *   net = brut − charges refacturées (ménage, réparations) + part des cautions retenues
 *         + TVA si le propriétaire est assujetti − retenue à la source (jamais sur les charges,
 *         la TVA ou la part des cautions : ce ne sont pas des revenus locatifs).
 *
 * « Nuitées consommées, jamais réservées » (CdC § 7.2) : un bon en attente ou un séjour pas
 * encore parti ne pèse rien ici.
 */
final class DetteProprietaire
{
    public function __construct(
        private readonly Parametres $parametres,
        private readonly RetenueALaSource $retenue,
    ) {}

    /**
     * Solde net actuel, tout confondu depuis l'origine : ce qui plafonne une demande de
     * paiement (CdC § 8.7). Recalculé à chaque appel — la retenue qu'il porte n'est figée
     * qu'au moment où un relevé ou une demande de paiement l'enregistre.
     *
     * @return array{brut: int, charges_refacturees: int, part_cautions: int, tva: int, retenue_taux: float, retenue_montant: int, net: int, deja_verse: int, solde_du: int}
     */
    public function soldeDu(Proprietaire $proprietaire): array
    {
        $calcul = $this->calculer($proprietaire, null, null);

        $dejaVerse = (int) Reglement::query()
            ->where('tiers_id', $proprietaire->utilisateur->id)->where('sens', 'decaissement')->where('etat', EtatDuReglement::Effectue)
            ->sum('montant');

        return [...$calcul, 'deja_verse' => $dejaVerse, 'solde_du' => max(0, $calcul['net'] - $dejaVerse)];
    }

    /**
     * Le calcul d'UN mois, pour le relevé (P3-PRO-03) : les charges qu'il reprend n'ont
     * jamais encore été déduites (`releve_id` encore nul).
     *
     * @return array{nuitees_consommees: int, brut: int, charges_refacturees: int, part_cautions: int, tva: int, retenue_taux: float, retenue_montant: int, retenue_motif: string, net: int, bons: Collection<int, BonDeMiseADisposition>, charges: Collection<int, ChargeProprietaire>}
     */
    public function calculerPourLaPeriode(Proprietaire $proprietaire, Carbon $premierJourDuMois): array
    {
        $debut = $premierJourDuMois->copy()->startOfMonth();
        $fin = $debut->copy()->addMonthNoOverflow();

        return $this->calculer($proprietaire, $debut, $fin);
    }

    /** @return array{nuitees_consommees: int, brut: int, charges_refacturees: int, part_cautions: int, tva: int, retenue_taux: float, retenue_montant: int, retenue_motif: string, net: int, bons: Collection<int, BonDeMiseADisposition>, charges: Collection<int, ChargeProprietaire>} */
    private function calculer(Proprietaire $proprietaire, ?Carbon $debut, ?Carbon $fin): array
    {
        $bons = $this->bonsConsommes($proprietaire, $debut, $fin);
        $nuitees = (int) $bons->sum('nuitees');
        $brut = (int) $bons->sum(fn (BonDeMiseADisposition $bon): int => $this->montantBrutDuBon($proprietaire, $bon));

        $charges = $this->chargesNonReprises($proprietaire, $debut, $fin);
        $chargesRefacturees = (int) $charges->sum('montant');

        $partCautions = $this->partDesCautions($proprietaire, $bons);
        $tva = $proprietaire->assujetti_tva ? (int) round($brut * (float) $this->parametres->valeur('taxes.tva') / 100) : 0;

        // La retenue ne porte QUE sur le revenu locatif brut — jamais sur un remboursement de
        // charge, la TVA collectée pour le compte de l'État, ou la part des cautions (dommages).
        ['taux' => $tauxRetenue, 'motif' => $motifRetenue] = $this->retenue->pour($proprietaire);
        $retenueMontant = (int) round($brut * $tauxRetenue / 100);

        $net = max(0, $brut - $chargesRefacturees + $partCautions + $tva - $retenueMontant);

        return [
            'nuitees_consommees' => $nuitees, 'brut' => $brut, 'charges_refacturees' => $chargesRefacturees,
            'part_cautions' => $partCautions, 'tva' => $tva, 'retenue_taux' => $tauxRetenue,
            'retenue_montant' => $retenueMontant, 'retenue_motif' => $motifRetenue, 'net' => $net,
            'bons' => $bons, 'charges' => $charges,
        ];
    }

    /** @return Collection<int, BonDeMiseADisposition> */
    public function bonsConsommes(Proprietaire $proprietaire, ?Carbon $debut, ?Carbon $finExclusive): Collection
    {
        return BonDeMiseADisposition::query()
            ->where('proprietaire_id', $proprietaire->id)->where('etat', 'valide')
            ->whereHas('sejour', function (Builder $q) use ($debut, $finExclusive): void {
                $q->whereIn('etat', [EtatDuSejour::Parti, EtatDuSejour::Cloture]);
                if ($debut !== null) {
                    $q->where('depart', '>=', $debut->toDateString());
                }
                if ($finExclusive !== null) {
                    $q->where('depart', '<', $finExclusive->toDateString());
                }
            })
            ->with('sejour')->get();
    }

    public function montantBrutDuBon(Proprietaire $proprietaire, BonDeMiseADisposition $bon): int
    {
        if ($proprietaire->mode_remuneration === ModeDeRemuneration::Commission) {
            $hebergementNetHt = (int) ($bon->sejour->devis['hebergement_net_ht'] ?? 0);
            $commission = (float) ($proprietaire->taux_commission ?? $this->parametres->valeur('proprietaires.commission'));

            return (int) round($hebergementNetHt * (1 - $commission / 100));
        }

        return (int) ($bon->prix_proprietaire_par_nuit ?? 0) * $bon->nuitees;
    }

    /** @return Collection<int, ChargeProprietaire> */
    private function chargesNonReprises(Proprietaire $proprietaire, ?Carbon $debut, ?Carbon $fin): Collection
    {
        return ChargeProprietaire::query()
            ->where('proprietaire_id', $proprietaire->id)->whereNull('releve_id')
            ->when($debut !== null, fn (Builder $q) => $q->where('periode', '>=', $debut->toDateString()))
            ->when($fin !== null, fn (Builder $q) => $q->where('periode', '<', $fin->toDateString()))
            ->get();
    }

    /** @param  Collection<int, BonDeMiseADisposition>  $bons */
    private function partDesCautions(Proprietaire $proprietaire, Collection $bons): int
    {
        $partEntreprise = (float) ($proprietaire->part_entreprise_cautions ?? $this->parametres->valeur('proprietaires.part_entreprise_cautions'));

        return (int) $bons->sum(function (BonDeMiseADisposition $bon) use ($partEntreprise): int {
            $retenue = (int) ($bon->sejour->caution_retenue ?? 0);

            return (int) round($retenue * (1 - $partEntreprise / 100));
        });
    }

    /** Avant tout reversement (relevé ou demande de paiement), CdC § 8.7 via le paramètre `proprietaires.regime_fiscal_obligatoire`. */
    public function exigerLeDossierComplet(Proprietaire $proprietaire): void
    {
        if ($proprietaire->interne) {
            return;
        }
        if (! (bool) $this->parametres->valeur('proprietaires.regime_fiscal_obligatoire')) {
            return;
        }
        if ($proprietaire->regime_fiscal === RegimeFiscal::NonRenseigne) {
            throw new ErreurMetier(
                'Le régime fiscal de ce propriétaire doit être renseigné avant tout reversement.',
                'regime_fiscal_manquant',
                422,
            );
        }
    }
}
