<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Services\Cautions;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Enums\EtatDuTransfert;
use App\Domain\Transferts\Models\Transfert;
use Illuminate\Support\Carbon;

/**
 * Marges par séjour et par transfert, et bloc « Bénéfices de l'entreprise » (CdC § 9.3).
 *
 * Marge par séjour telle que demandée au § 9.3 : « Facturé HT − reversé au propriétaire −
 * coût ménage − commission canal ». Seul le premier terme (reversé au propriétaire, via
 * `prix_proprietaire_par_nuit`, figé à la réservation) est déjà posé dans le code ; le coût
 * ménage et la commission de canal ne sont pas encore modélisés (aucune mission de ménage ne
 * porte de coût, aucun canal externe ne porte de barème de commission) — ils sont donc
 * comptés à zéro ici et clairement signalés dans la réponse, plutôt que d'être inventés.
 */
final class Marges
{
    public function __construct(private readonly Cautions $cautions) {}

    /** Marge par séjour, triée du moins rentable au plus rentable (CdC § 9.3). */
    public function parSejour(Carbon $du, Carbon $au): array
    {
        $sejours = Sejour::query()->with('logement.residence.proprietaire')
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->whereDate('arrivee', '>=', $du)->whereDate('arrivee', '<=', $au)
            ->get();

        $lignes = $sejours->map(function (Sejour $s): array {
            $factureHt = (int) ($s->devis['total_ht'] ?? 0);
            $reverseProprietaire = (int) ($s->prix_proprietaire_par_nuit ?? 0) * $s->nombreDeNuits();

            return [
                'sejour' => $s->reference,
                'proprietaire' => $s->logement->residence->proprietaire->nomAffiche(),
                'logement' => $s->logement->nom,
                'facture_ht' => $factureHt,
                'reverse_proprietaire' => $reverseProprietaire,
                'cout_menage' => 0,
                'commission_canal' => 0,
                'marge' => $factureHt - $reverseProprietaire,
            ];
        })->sortBy('marge')->values();

        return [
            'lignes' => $lignes->all(),
            'note' => 'Coût ménage et commission de canal comptés à zéro : non encore modélisés dans le code.',
            'total_marge' => (int) $lignes->sum('marge'),
        ];
    }

    /** Marge par transfert : facturé − versé au chauffeur (CdC § 9.3). */
    public function parTransfert(Carbon $du, Carbon $au): array
    {
        $transferts = Transfert::query()->with('sejour', 'chauffeur.utilisateur')
            ->where('etat', EtatDuTransfert::Termine)
            ->whereDate('date_heure_prevue', '>=', $du)->whereDate('date_heure_prevue', '<=', $au)
            ->get();

        $lignes = $transferts->map(fn (Transfert $t): array => [
            'transfert' => $t->reference,
            'sejour' => $t->sejour?->reference,
            'chauffeur' => $t->chauffeur?->utilisateur?->nomComplet(),
            'facture' => $t->montant,
            'verse_au_chauffeur' => (int) ($t->montant_verse_au_chauffeur ?? 0),
            'marge' => $t->montant - (int) ($t->montant_verse_au_chauffeur ?? 0),
        ])->sortBy('marge')->values();

        return ['lignes' => $lignes->all(), 'total_marge' => (int) $lignes->sum('marge')];
    }

    /** Récapitulatif « Bénéfices de l'entreprise » (CdC § 9.3). */
    public function recapitulatif(Carbon $du, Carbon $au): array
    {
        $sejours = $this->parSejour($du, $au);
        $transferts = $this->parTransfert($du, $au);

        return [
            'benefices_sejours' => $sejours['total_marge'],
            'benefices_transferts' => $transferts['total_marge'],
            'benefices_entreprise' => $sejours['total_marge'] + $transferts['total_marge'],
        ];
    }

    /**
     * État des cautions, séjour par séjour : retenue, restituée, détenue (CdC § 9.3).
     *
     * Les montants viennent de `Cautions::soldeDe()`, c'est-à-dire des MOUVEMENTS RÉELS du
     * guichet Cautions (dépôts finalisés, retenues actées, restitutions décaissées) — et non
     * de l'état du séjour. Cette version lisait auparavant la colonne `caution_retenue` et
     * DÉDUISAIT la restitution de l'état « parti / clôturé », ce qui déclarait restituée une
     * caution encore en caisse : la restitution est un décaissement à part entière, qui peut
     * n'intervenir que des jours après le départ. Un état comptable qui sous-évalue une dette
     * est plus dangereux qu'un état absent.
     */
    public function etatDesCautions(Carbon $du, Carbon $au): array
    {
        $sejours = Sejour::query()
            ->where('caution', '>', 0)
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->whereDate('arrivee', '>=', $du)->whereDate('arrivee', '<=', $au)
            ->get();

        $lignes = $sejours->map(function (Sejour $s): array {
            $solde = $this->cautions->soldeDe($s);

            return [
                'sejour' => $s->reference,
                'caution' => $s->caution,
                'retenue' => $solde['retenue'],
                'restituee' => $solde['restituee'],
                'detenue' => $solde['detenue'],
            ];
        });

        return [
            'lignes' => $lignes->values()->all(),
            'totaux' => [
                'caution' => (int) $lignes->sum('caution'),
                'retenue' => (int) $lignes->sum('retenue'),
                'restituee' => (int) $lignes->sum('restituee'),
                'detenue' => (int) $lignes->sum('detenue'),
            ],
        ];
    }
}
