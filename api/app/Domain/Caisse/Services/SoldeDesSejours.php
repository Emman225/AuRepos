<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Imputation;
use App\Domain\Sejours\Models\Sejour;

/**
 * Où en est le règlement d'un séjour.
 *
 *   encaissé  — seuls les règlements EFFECTUÉS comptent (« tant que le circuit n'est pas terminé,
 *               la somme ne compte pas comme payée ») ;
 *   en cours  — saisi, pas encore effectué : la somme RÉSERVE SA PLACE, on ne peut pas encaisser deux fois ;
 *   reste dû  — net à payer − encaissé − en cours. La caution a son propre guichet : elle n'est pas ici.
 */
final class SoldeDesSejours
{
    /** @return array{net_a_payer: int, encaisse: int, encaisse_hors_avance: int, en_cours: int, reste_du: int, solde: bool, acompte_atteint: bool} */
    public function de(Sejour $sejour): array
    {
        $parEtat = Imputation::query()
            ->join('reglements', 'reglements.id', '=', 'imputations_reglement.reglement_id')
            ->where('imputations_reglement.affaire_type', $sejour->getMorphClass())
            ->where('imputations_reglement.affaire_id', $sejour->id)
            ->where('reglements.sens', 'encaissement')
            ->groupBy('reglements.etat')
            ->selectRaw('reglements.etat as etat, sum(imputations_reglement.montant) as total')
            ->pluck('total', 'etat');

        $encaisse = (int) ($parEtat[EtatDuReglement::Effectue->value] ?? 0);
        // Ce que le client a VERSÉ pour ce séjour, hors avance imputée d'office (argent déposé avant, pour autre chose).
        $horsAvance = (int) Imputation::query()
            ->join('reglements', 'reglements.id', '=', 'imputations_reglement.reglement_id')
            ->where('imputations_reglement.affaire_type', $sejour->getMorphClass())->where('imputations_reglement.affaire_id', $sejour->id)
            ->where('reglements.sens', 'encaissement')->where('reglements.etat', EtatDuReglement::Effectue->value)
            ->where('reglements.mode', '<>', 'avance')->sum('imputations_reglement.montant');
        $enCours = 0;
        foreach (EtatDuReglement::cases() as $etat) {
            if ($etat->enCours()) {
                $enCours += (int) ($parEtat[$etat->value] ?? 0);
            }
        }

        return [
            'net_a_payer' => $sejour->net_a_payer,
            'encaisse' => $encaisse,
            'encaisse_hors_avance' => $horsAvance,
            'en_cours' => $enCours,
            'reste_du' => max(0, $sejour->net_a_payer - $encaisse - $enCours),
            'solde' => $encaisse >= $sejour->net_a_payer,
            'acompte_atteint' => $encaisse >= $sejour->acompte_exige,
        ];
    }
}
