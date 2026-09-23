<?php

namespace App\Domain\Tarification\Calcul;

use App\Domain\Parametres\Services\Parametres;
use App\Domain\Tarification\Models\Supplement;
use App\Domain\Tarification\Services\Tarifs;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Carbon;

/**
 * MOTEUR UNIQUE du prix d'un séjour. Le site, l'application mobile et le back office
 * n'envoient que des intentions ; ce calcul est la seule autorité (CdC § 4 et § 13.1).
 *
 * Formule du cahier des charges (§ 5.4) :
 *
 *   net à payer = [ (hébergement HT net + extras HT + repas HT) × (1 + TVA)
 *                   + transfert × (1 + TVA transfert) ] × (1 + TDT)
 *                 + taxe de séjour
 *   total       = net à payer + caution
 *
 * Lectures retenues (consignées dans PLAN-REALISATION.md, à confirmer avec le client) :
 *   - les tarifs de la grille et le prix de vente sont HORS TAXES ;
 *   - chaque taxe est arrondie au franc, à l'arrondi commercial ;
 *   - le supplément « week-end » vise les nuits du vendredi et du samedi.
 *
 * Mon Gravier calculait le total deux fois (application et serveur) et il est arrivé
 * que les deux divergent (443 F affichés, 437 F facturés). Ici il n'y a qu'un calcul.
 */
final class CalculDuSejour
{
    /** Nuits dites « de week-end » : celle du vendredi au samedi et celle du samedi au dimanche. */
    private const JOURS_DE_WEEK_END = [Carbon::FRIDAY, Carbon::SATURDAY];

    public function __construct(
        private readonly Tarifs $tarifs,
        private readonly Parametres $parametres,
    ) {}

    public function calculer(DemandeDeCalcul $demande): DevisDeSejour
    {
        $this->controler($demande);
        $nuits = $demande->nombreDeNuits();

        // 1. Hébergement : nuit par nuit. Un prix négocié avec le client prime sur toute la grille.
        $nuitees = $this->tarifs->nuitees($demande->logement, $demande->arrivee, $demande->depart);
        if ($demande->tarifNegocieParNuit !== null) {
            $nuitees = array_map(fn (array $n): array => [...$n, 'tarif' => $demande->tarifNegocieParNuit, 'origine' => 'prix négocié du client'], $nuitees);
        }
        $brut = array_sum(array_column($nuitees, 'tarif'));

        // 2. Suppléments.
        $supplements = $this->supplements($demande, $nuitees);
        $supplementsHt = array_sum(array_column($supplements, 'montant'));
        $base = $brut + $supplementsHt;

        // 3. Remise en pourcentage : elle se calcule sur le hors taxes (CdC § 6.1).
        $remise = self::pourcentage($base, $demande->remisePourcentage);

        // 4. Taux lus UNE fois : ce sont eux qui seront figés sur le séjour.
        $tauxTva = $demande->tvaHebergementApplicable ? (float) $this->parametres->valeur('taxes.tva') : 0.0;
        $tvaSurTransferts = (bool) $this->parametres->valeur('taxes.tva_sur_transferts');
        $tauxTvaTransfert = $demande->tvaTransfertApplicable && $tvaSurTransferts ? (float) $this->parametres->valeur('taxes.tva') : 0.0;
        $tauxTdt = (float) $this->parametres->valeur('taxes.tdt');

        // 5. Taxe de séjour : hors base TVA, par nuitée et par occupant (ou par logement).
        [$occupantsTaxables, $taxeDeSejour] = $this->taxeDeSejour($demande, $nuits);

        // 6. Code promo et points : jamais sous le minimum à payer (CdC § 4).
        $extrasHt = array_sum(array_column($demande->extras, 'montant_ht'));
        $demandees = array_sum(array_column($demande->reductions, 'montant'));
        $plancherHt = $this->plancherHt($taxeDeSejour, $tauxTva, $tauxTdt);
        $accordees = max(0, min($demandees, $base - $remise - $plancherHt));
        $netHt = $base - $remise - $accordees;

        // 7. Taxes.
        $tva = self::pourcentage($netHt + $extrasHt, $tauxTva);
        $tvaTransfert = self::pourcentage($demande->transfertHt, $tauxTvaTransfert);
        $ttc = $netHt + $extrasHt + $tva + $demande->transfertHt + $tvaTransfert;
        // La TDT se calcule sur le net TTC de l'hébergement et des prestations (CdC § 5.4).
        $tdt = self::pourcentage($ttc, $tauxTdt);

        $net = $ttc + $tdt + $taxeDeSejour;
        $caution = (int) $demande->logement->caution;

        return new DevisDeSejour(
            nombreDeNuits: $nuits,
            nuitees: $nuitees,
            hebergementBrutHt: $brut,
            supplements: $supplements,
            supplementsHt: $supplementsHt,
            remisePourcentage: $demande->remisePourcentage,
            remiseHt: $remise,
            reductions: $demande->reductions,
            reductionsHt: $accordees,
            reductionsPlafonnees: $accordees < $demandees,
            hebergementNetHt: $netHt,
            extras: $demande->extras,
            extrasHt: $extrasHt,
            tvaHebergementEtExtras: $tva,
            transfertHt: $demande->transfertHt,
            tvaTransfert: $tvaTransfert,
            totalTtc: $ttc,
            tdt: $tdt,
            occupantsTaxables: $occupantsTaxables,
            taxeDeSejour: $taxeDeSejour,
            netAPayer: $net,
            caution: $caution,
            totalAvecCaution: $net + $caution,
            taux: [
                'tva' => $tauxTva, 'tva_transfert' => $tauxTvaTransfert, 'tdt' => $tauxTdt,
                'taxe_sejour_montant' => (int) $this->parametres->valeur('taxes.sejour_montant'),
                'taxe_sejour_base' => (string) $this->parametres->valeur('taxes.sejour_base'),
            ],
        );
    }

    private function controler(DemandeDeCalcul $demande): void
    {
        $logement = $demande->logement;
        $nuits = $demande->nombreDeNuits();

        if ($nuits < 1) {
            throw new ErreurMetier('Le départ doit suivre l’arrivée d’au moins une nuit.', 'periode_invalide', 422);
        }
        if ($demande->adultes < 1) {
            throw new ErreurMetier('Un séjour compte au moins un adulte.', 'occupants_invalides', 422);
        }
        if ($demande->occupants() > $logement->capacite_maximale) {
            throw new ErreurMetier("Ce logement accueille au plus {$logement->capacite_maximale} personnes.", 'capacite_depassee', 422);
        }
        if ($demande->remisePourcentage < 0 || $demande->remisePourcentage > 100) {
            throw new ErreurMetier('La remise doit être comprise entre 0 et 100 %.', 'remise_invalide', 422);
        }

        $minimum = $logement->duree_minimale ?? (int) $this->parametres->valeur('sejours.duree_minimale');
        if ($nuits < $minimum) {
            throw new ErreurMetier("Ce logement se réserve pour {$minimum} nuit(s) au minimum.", 'duree_minimale', 422);
        }
        if ($logement->duree_maximale !== null && $nuits > $logement->duree_maximale) {
            throw new ErreurMetier("Ce logement se réserve pour {$logement->duree_maximale} nuits au maximum.", 'duree_maximale', 422);
        }
    }

    /**
     * @param  list<array{date: string, saison: string|null, tarif: int, origine: string}>  $nuitees
     * @return list<array{code: string, libelle: string, quantite: int, montant_unitaire: int, montant: int}>
     */
    private function supplements(DemandeDeCalcul $demande, array $nuitees): array
    {
        $nuits = count($nuitees);
        $occupants = $demande->occupants();
        $enPlus = max(0, $occupants - $demande->logement->capacite_de_base);
        $nuitsDeWeekEnd = count(array_filter($nuitees, fn (array $n) => in_array(Carbon::parse($n['date'])->dayOfWeek, self::JOURS_DE_WEEK_END, true)));

        // Pour chaque supplément : s'applique-t-il, et combien de fois selon son mode de calcul ?
        $cas = [
            'occupant_supplementaire' => [$enPlus > 0, ['par_nuit_et_par_personne' => $enPlus * $nuits, 'par_nuit' => $nuits, 'forfait' => 1]],
            'week_end' => [$nuitsDeWeekEnd > 0, ['par_nuit_et_par_personne' => $occupants * $nuitsDeWeekEnd, 'par_nuit' => $nuitsDeWeekEnd, 'forfait' => 1]],
            'arrivee_tardive' => [$demande->arriveeTardive, ['par_nuit_et_par_personne' => $occupants, 'par_nuit' => 1, 'forfait' => 1]],
            'depart_tardif' => [$demande->departTardif, ['par_nuit_et_par_personne' => $occupants, 'par_nuit' => 1, 'forfait' => 1]],
        ];

        $lignes = [];
        foreach ($cas as $code => [$applicable, $quantites]) {
            $supplement = $applicable ? $this->supplementPour($code, $demande->logement->type_logement_id) : null;
            if ($supplement === null || $supplement->montant === 0) {
                continue;
            }
            $quantite = $quantites[$supplement->mode];
            $lignes[] = [
                'code' => $code, 'libelle' => $supplement->nom, 'quantite' => $quantite,
                'montant_unitaire' => $supplement->montant, 'montant' => $supplement->montant * $quantite,
            ];
        }

        return $lignes;
    }

    /** Le supplément propre au type de logement l'emporte sur le supplément général. */
    private function supplementPour(string $code, int $typeLogementId): ?Supplement
    {
        return Supplement::query()->where('code', $code)->where('actif', true)
            ->where(fn ($q) => $q->where('type_logement_id', $typeLogementId)->orWhereNull('type_logement_id'))
            ->orderByRaw('type_logement_id IS NULL')
            ->first();
    }

    /** @return array{0: int, 1: int} [occupants taxables, montant] */
    private function taxeDeSejour(DemandeDeCalcul $demande, int $nuits): array
    {
        $montant = (int) $this->parametres->valeur('taxes.sejour_montant');
        $taxables = $demande->adultes + ((bool) $this->parametres->valeur('taxes.sejour_enfants_exoneres') ? 0 : $demande->enfants);

        return $this->parametres->valeur('taxes.sejour_base') === 'logement'
            ? [$taxables, $montant * $nuits]
            : [$taxables, $montant * $nuits * $taxables];
    }

    /** Hébergement HT en dessous duquel promo et points ne peuvent pas faire descendre le séjour. */
    private function plancherHt(int $taxeDeSejour, float $tauxTva, float $tauxTdt): int
    {
        $minimum = max(0, (int) $this->parametres->valeur('general.minimum_a_payer') - $taxeDeSejour);

        return (int) ceil($minimum / ((1 + $tauxTva / 100) * (1 + $tauxTdt / 100)));
    }

    /**
     * Pourcentage d'un montant entier, arrondi au franc (arrondi commercial : 0,5 monte).
     *
     * Tout en ENTIERS : en virgule flottante, un résultat censé valoir exactement 1 234,5
     * peut être stocké 1 234,4999… et s'arrondir un franc trop bas. Le taux est donc porté
     * en centièmes de pour cent (18 % → 1800, 7,5 % → 750), puis la division est entière.
     */
    public static function pourcentage(int $montant, float $taux): int
    {
        $centiemes = (int) round($taux * 100);

        return intdiv($montant * $centiemes + 5000, 10000);
    }
}
