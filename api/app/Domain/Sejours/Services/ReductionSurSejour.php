<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Comptes\Models\User;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Domain\Validation\Models\ChangementAValider;
use App\Domain\Validation\Services\DoubleValidation;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Réduction sur un séjour (CdC § 6.1) : « un administrateur saisit un pourcentage, le
 * trésorier (validant 2) confirme ; la remise se calcule sur le hors taxes. Sans trésorier
 * désigné, rien ne se passe et l'écran le signale en rouge. »
 *
 * Passe par la double validation générique (prix de vente, pourcentage entreprise), avec
 * UNE contrainte de plus : le validateur doit être PRÉCISÉMENT le trésorier désigné dans
 * les Paramètres, pas n'importe quel administrateur.
 *
 * Une fois confirmée, la réduction s'applique au devis déjà FIGÉ (CdC § 5.4) : elle relit
 * les TAUX GELÉS à la réservation (TVA, TDT…), jamais ceux, peut-être différents aujourd'hui,
 * des Paramètres — sinon confirmer une simple remise changerait aussi le régime fiscal du
 * séjour à l'insu de tous.
 */
final class ReductionSurSejour
{
    private const CHAMP = 'reduction_pourcentage';

    public function __construct(
        private readonly DoubleValidation $doubleValidation,
        private readonly Parametres $parametres,
    ) {}

    public function proposer(Sejour $sejour, float $pourcentage, string $motif, User $administrateur): ChangementAValider
    {
        if (! $this->parametres->tresorierDesigne()) {
            throw new ErreurMetier('Aucun trésorier n’est désigné dans les Paramètres : aucune réduction n’est possible.', 'tresorier_non_designe', 422);
        }
        if ($pourcentage < 0 || $pourcentage > 100) {
            throw new ErreurMetier('La réduction doit être comprise entre 0 et 100 %.', 'reduction_invalide', 422);
        }

        $changement = $this->doubleValidation->proposer($sejour, self::CHAMP, $pourcentage, $administrateur, $motif);
        $sejour->forceFill(['reduction_motif' => $motif])->saveQuietly();

        return $changement;
    }

    /** Seul LE trésorier désigné confirme — pas « un autre administrateur » comme pour le reste. */
    public function confirmer(ChangementAValider $changement, User $tresorier): Sejour
    {
        $designe = $this->parametres->valeur('gestionnaires.validant_2_id');
        if ($designe === null || (int) $designe !== $tresorier->id) {
            throw new ErreurMetier('Seul le trésorier désigné dans les Paramètres peut confirmer une réduction.', 'tresorier_requis', 403);
        }

        $sejour = DB::transaction(function () use ($changement, $tresorier): Sejour {
            $this->doubleValidation->valider($changement, $tresorier);
            /** @var Sejour $sejour */
            $sejour = $changement->sujet->refresh();
            $this->recalculer($sejour);

            return $sejour->refresh();
        });

        return $sejour;
    }

    /**
     * Rejoue les mêmes étapes que le moteur (CalculDuSejour::pourcentage, arithmétique
     * ENTIÈRE) à partir du devis figé, avec la nouvelle remise. Les points et le code
     * promo déjà accordés ne sont JAMAIS repris : seule la remise nouvelle respecte le
     * plancher du minimum à payer, quitte à être elle-même plafonnée.
     */
    private function recalculer(Sejour $sejour): void
    {
        $d = $sejour->devis;
        if ($d === null) {
            throw new ErreurMetier('Ce séjour n’a pas de devis figé : impossible d’y appliquer une réduction.', 'devis_absent', 422);
        }

        $pourcentage = (float) $sejour->reduction_pourcentage;
        $base = (int) $d['hebergement_brut_ht'] + (int) $d['supplements_ht'];
        $reductionsHt = (int) $d['reductions_ht'];
        $extrasHt = (int) $d['extras_ht'];
        $transfertHt = (int) $d['transfert_ht'];
        $taxeDeSejour = (int) $d['taxe_de_sejour'];
        $tauxTva = (float) $d['taux']['tva'];
        $tauxTvaTransfert = (float) $d['taux']['tva_transfert'];
        $tauxTdt = (float) $d['taux']['tdt'];

        $minimum = max(0, (int) $this->parametres->valeur('general.minimum_a_payer') - $taxeDeSejour);
        $plancherHt = (int) ceil($minimum / ((1 + $tauxTva / 100) * (1 + $tauxTdt / 100)));

        $remiseDemandee = CalculDuSejour::pourcentage($base, $pourcentage);
        // Les réductions déjà accordées (points, code promo) ne sont jamais rognées : seule
        // la remise nouvelle cède devant le plancher.
        $remiseHt = max(0, min($remiseDemandee, $base - $reductionsHt - $plancherHt));

        $netHt = $base - $remiseHt - $reductionsHt;
        $tva = CalculDuSejour::pourcentage($netHt + $extrasHt, $tauxTva);
        $tvaTransfert = CalculDuSejour::pourcentage($transfertHt, $tauxTvaTransfert);
        $ttc = $netHt + $extrasHt + $tva + $transfertHt + $tvaTransfert;
        $tdt = CalculDuSejour::pourcentage($ttc, $tauxTdt);
        $net = $ttc + $tdt + $taxeDeSejour;

        // L'acompte suit la même proportion : un client qui devait 30 % du prix initial
        // en doit toujours 30 %, pas un montant devenu incohérent avec le nouveau total.
        $nouvelAcompte = $sejour->net_a_payer > 0
            ? (int) round($sejour->acompte_exige * $net / $sejour->net_a_payer)
            : $sejour->acompte_exige;

        $sejour->forceFill([
            'devis' => [
                ...$d, 'remise_pourcentage' => $pourcentage, 'remise_ht' => $remiseHt, 'hebergement_net_ht' => $netHt,
                'total_ht' => $netHt + $extrasHt + $transfertHt, 'tva_hebergement_et_extras' => $tva, 'tva_transfert' => $tvaTransfert,
                'total_tva' => $tva + $tvaTransfert, 'total_ttc' => $ttc, 'tdt' => $tdt, 'autres_taxes' => $tdt + $taxeDeSejour,
                'net_a_payer' => $net, 'total_avec_caution' => $net + $sejour->caution,
            ],
            'net_a_payer' => $net,
            'acompte_exige' => $nouvelAcompte,
        ])->saveQuietly();
    }
}
