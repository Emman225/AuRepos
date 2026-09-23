<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Domain\Tarification\Calcul\DemandeDeCalcul;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Prolongation et départ anticipé (P2-SEJ-03, CdC § 6.3) : un séjour déjà ARRIVÉ change de
 * date de départ. La disponibilité est REVÉRIFIÉE (Calendrier::occuper, MÊME contrainte
 * d'exclusion que toute réservation) et le devis est ENTIÈREMENT recalculé par le moteur
 * unique (CalculDuSejour — jamais un second calcul ad hoc), sur les nuitées réellement
 * consommées (arrivée d'origine → nouveau départ) : suppléments, extras et transfert déjà
 * figés au séjour sont repris tels quels, seules les dates et donc l'hébergement bougent.
 */
final class ProlongationDeSejour
{
    public function __construct(
        private readonly CalculDuSejour $calcul,
        private readonly Calendrier $calendrier,
        private readonly JournalAudit $journal,
    ) {}

    public function modifierLeDepart(Sejour $sejour, Carbon $nouveauDepart, User $auteur): Sejour
    {
        if ($sejour->etat !== EtatDuSejour::Arrive) {
            throw new ErreurMetier('Seul un séjour déjà arrivé peut être prolongé ou raccourci.', 'sejour_non_arrive', 422);
        }
        if ($nouveauDepart->toDateString() === $sejour->depart->toDateString()) {
            throw new ErreurMetier('La nouvelle date de départ est identique à l’actuelle.', 'date_inchangee', 422);
        }
        if ($nouveauDepart->lte($sejour->arrivee)) {
            throw new ErreurMetier('Le départ doit rester après l’arrivée.', 'periode_invalide', 422);
        }
        if ($nouveauDepart->lt(Carbon::today())) {
            throw new ErreurMetier('Le départ ne peut pas être ramené à une date déjà passée.', 'periode_invalide', 422);
        }

        $ancienDepart = $sejour->depart;
        $client = Client::de($sejour->client);
        $devisActuel = $sejour->devis ?? [];

        $devis = $this->calcul->calculer(new DemandeDeCalcul(
            logement: $sejour->logement,
            arrivee: $sejour->arrivee,
            depart: $nouveauDepart,
            adultes: $sejour->adultes,
            enfants: $sejour->enfants,
            reductions: Arr::get($devisActuel, 'reductions', []),
            extras: Arr::get($devisActuel, 'extras', []),
            transfertHt: (int) Arr::get($devisActuel, 'transfert_ht', 0),
            tvaHebergementApplicable: $client->tva_hebergement,
            tvaTransfertApplicable: $client->tva_transfert,
        ));

        return DB::transaction(function () use ($sejour, $nouveauDepart, $devis, $ancienDepart, $auteur): Sejour {
            $ancienNetAPayer = $sejour->net_a_payer;

            $sejour->forceFill([
                'depart' => $nouveauDepart->toDateString(),
                'devis' => $devis->toArray(),
                'net_a_payer' => $devis->netAPayer,
                'caution' => $devis->caution,
            ])->saveQuietly();

            // Revérifie la disponibilité MAINTENANT : la base refuse si une prolongation chevauche une autre occupation.
            $this->calendrier->occuper($sejour);

            $sens = $nouveauDepart->gt($ancienDepart) ? 'prolongé' : 'écourté (départ anticipé)';
            $this->journal->consigner(
                'sejour_depart_modifie',
                $sejour->libelleAudit()." : séjour {$sens}, du ".$ancienDepart->format('d/m/Y').' au '.$nouveauDepart->format('d/m/Y').'. Net à payer : '.number_format($devis->netAPayer, 0, ',', ' ').' F.',
                $sejour,
                ['depart' => $ancienDepart->toDateString(), 'net_a_payer' => $ancienNetAPayer],
                ['depart' => $nouveauDepart->toDateString(), 'net_a_payer' => $devis->netAPayer],
                $auteur,
            );

            return $sejour->refresh();
        });
    }
}
