<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Imputation;
use Illuminate\Database\Eloquent\Model;

/**
 * Où en est le règlement d'une consommation demandée PENDANT un séjour (transfert, extra…) —
 * même principe que App\Domain\Caisse\Services\SoldeDesSejours, mais pour une affaire dont le
 * montant dû est FIXE et connu d'avance (jamais recalculé), imputée par
 * imputations_reglement comme n'importe quelle autre affaire (P2-TRF-03).
 */
final class SoldeDesConsommations
{
    /** @return array{montant_du: int, encaisse: int, en_cours: int, reste_du: int} */
    public function de(Model $consommation, int $montantDu): array
    {
        $parEtat = Imputation::query()
            ->join('reglements', 'reglements.id', '=', 'imputations_reglement.reglement_id')
            ->where('imputations_reglement.affaire_type', $consommation->getMorphClass())
            ->where('imputations_reglement.affaire_id', $consommation->getKey())
            ->where('reglements.sens', 'encaissement')
            ->groupBy('reglements.etat')
            ->selectRaw('reglements.etat as etat, sum(imputations_reglement.montant) as total')
            ->pluck('total', 'etat');

        $encaisse = (int) ($parEtat[EtatDuReglement::Effectue->value] ?? 0);
        $enCours = 0;
        foreach (EtatDuReglement::cases() as $etat) {
            if ($etat->enCours()) {
                $enCours += (int) ($parEtat[$etat->value] ?? 0);
            }
        }

        return [
            'montant_du' => $montantDu,
            'encaisse' => $encaisse,
            'en_cours' => $enCours,
            'reste_du' => max(0, $montantDu - $encaisse - $enCours),
        ];
    }
}
