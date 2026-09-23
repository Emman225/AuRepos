<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Caisse\Services\Avances;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Comptes\Models\User;
use App\Domain\Fidelite\Services\PointsDeFidelite;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Machine à états du séjour (CdC § 4) — le SEUL endroit où un séjour change d'état.
 *
 *   demandé ──▶ confirmé ──▶ arrivé ──▶ parti ──▶ clôturé
 *      │            ├──▶ no-show
 *      └────────────┴──▶ annulé
 *
 * Chaque passage met le calendrier à jour (annulé et no-show libèrent les dates) et laisse
 * une trace. Les conditions métier d'une transition (paiement vérifié avant de confirmer,
 * caution encaissée avant le check-in…) sont posées par les services qui l'appellent.
 */
final class CycleDuSejour
{
    /** @var array<string, list<EtatDuSejour>> */
    private const TRANSITIONS = [
        'demande' => [EtatDuSejour::Confirme, EtatDuSejour::Annule],
        'confirme' => [EtatDuSejour::Arrive, EtatDuSejour::Annule, EtatDuSejour::NoShow],
        'arrive' => [EtatDuSejour::Parti],
        'parti' => [EtatDuSejour::Cloture],
        'cloture' => [], 'annule' => [], 'no_show' => [],
    ];

    public function __construct(
        private readonly Calendrier $calendrier,
        private readonly JournalAudit $journal,
        private readonly SoldeDesSejours $soldes,
        private readonly Avances $avances,
        private readonly PointsDeFidelite $points,
    ) {}

    public function peutPasser(Sejour $sejour, EtatDuSejour $vers): bool
    {
        return in_array($vers, self::TRANSITIONS[$sejour->etat->value], true);
    }

    /** @param array<string, mixed> $complements colonnes à poser en même temps (dates, motif…) */
    public function passer(Sejour $sejour, EtatDuSejour $vers, ?User $auteur = null, array $complements = [], ?string $motif = null): void
    {
        if (! $this->peutPasser($sejour, $vers)) {
            throw new ErreurMetier(
                'Un séjour « '.$sejour->etat->libelle().' » ne peut pas passer à « '.$vers->libelle().' ».',
                'transition_impossible',
            );
        }

        DB::transaction(function () use ($sejour, $vers, $auteur, $complements, $motif): void {
            $avant = $sejour->etat;
            $sejour->forceFill(['etat' => $vers, ...$complements])->saveQuietly();
            $this->calendrier->occuper($sejour); // libère les dates si le nouvel état ne les tient plus

            $this->journal->consigner(
                'sejour_'.$vers->value,
                $sejour->libelleAudit().' : '.$avant->libelle().' → '.$vers->libelle().($motif ? " ({$motif})" : '').'.',
                $sejour, ['etat' => $avant->value], ['etat' => $vers->value], $auteur,
            );
        });
    }

    /**
     * Annulation. La part retenue suit la politique FIGÉE sur le séjour à la réservation :
     * gratuite jusqu'à N jours avant l'arrivée, puis un pourcentage du net à payer.
     * Le remboursement éventuel est un décaissement, avec son circuit de preuve (P2-SEJ-06).
     */
    public function annuler(Sejour $sejour, ?User $auteur, string $motif): void
    {
        $retenu = $this->montantRetenuSiAnnulation($sejour);
        $simpleDemande = $sejour->etat === EtatDuSejour::Demande;

        $this->passer($sejour, EtatDuSejour::Annule, $auteur, [
            'annule_le' => now(), 'motif_annulation' => $motif, 'montant_retenu_annulation' => $retenu, 'expire_le' => null,
        ], $motif);

        // Rien n'a été servi : l'avance consommée par une simple demande revient au client.
        // (Le remboursement d'un séjour CONFIRMÉ est un décaissement instruit par la réception — P2-SEJ-06.)
        if ($simpleDemande) {
            $this->avances->recrediterPour($sejour, $motif);
        }

        // « Points repris à l’annulation remboursée » (CdC § 4) : le client récupère ceux qu’il avait
        // utilisés, et rend ceux que les règlements de ce séjour lui avaient donnés.
        $this->points->reprendreSur($sejour, $motif);
    }

    /**
     * No-show automatique (P2-SEJ-05, CdC § 4) : un séjour confirmé jamais arrivé, passé le
     * délai paramétrable (`sejours.heure_no_show`, à J+1). MÊME formule de retenue que
     * l'annulation (`montantRetenuSiAnnulation`) : c'est le même concept — « ce qui reste
     * retenu, et pourquoi, sur un séjour qui ne s'est pas honoré » — seule sa propre date,
     * `no_show_le`, lui est dédiée. Les dates se libèrent d'elles-mêmes (occupeLeCalendrier()).
     */
    public function declarerNoShow(Sejour $sejour, string $motif): void
    {
        $retenu = $this->montantRetenuSiAnnulation($sejour);

        $this->passer($sejour, EtatDuSejour::NoShow, null, [
            'no_show_le' => now(), 'motif_annulation' => $motif, 'montant_retenu_annulation' => $retenu,
        ], $motif);
    }

    /** Ce que la politique retiendrait si le séjour était annulé MAINTENANT. */
    public function montantRetenuSiAnnulation(Sejour $sejour): int
    {
        // Une simple demande n'a rien encaissé : rien à retenir.
        if ($sejour->etat === EtatDuSejour::Demande) {
            return 0;
        }

        $joursAvantArrivee = (int) Carbon::today()->diffInDays($sejour->arrivee, false);
        if ($joursAvantArrivee >= $sejour->annulation_delai_jours) {
            return 0;
        }

        return CalculDuSejour::pourcentage($sejour->net_a_payer, $sejour->annulation_pourcentage_retenu);
    }

    /** Une demande non réglée expire et libère ses dates (CdC § 5.2). Rend le nombre de demandes expirées. */
    public function expirerLesDemandes(): int
    {
        $expirees = Sejour::query()->where('etat', EtatDuSejour::Demande)->whereNotNull('expire_le')->where('expire_le', '<=', now())->get();

        $nombre = 0;
        foreach ($expirees as $sejour) {
            // On n'annule JAMAIS la réservation d'un client qui a payé : versement effectué ou encore dans le
            // circuit de preuve, ou acompte atteint. La réception la confirmera ou instruira le dossier.
            // Une avance imputée d'office, elle, n'est pas un nouveau versement : si elle ne couvre pas
            // l'acompte, la demande expire et l'avance revient au client.
            $solde = $this->soldes->de($sejour);
            if ($solde['encaisse_hors_avance'] > 0 || $solde['en_cours'] > 0 || ($solde['acompte_atteint'] && $sejour->acompte_exige > 0)) {
                continue;
            }

            $this->annuler($sejour, null, 'Demande non réglée dans le délai : dates libérées.');
            $nombre++;
        }

        return $nombre;
    }
}
