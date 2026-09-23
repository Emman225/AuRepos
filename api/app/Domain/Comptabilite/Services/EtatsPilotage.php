<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\Residence;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Models\Transfert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * États de pilotage commercial (CdC § 9.1) : chiffre d'affaires, occupation, annulations,
 * disponibilité, marge et prévisionnel. Lecture seule : aucune de ces méthodes n'écrit
 * quoi que ce soit, elles agrègent des montants déjà figés sur les séjours (`Sejour::devis`)
 * ou déjà enregistrés (`Reglement`, `Transfert`).
 *
 * Le chiffre d'affaires porte sur les séjours qui ont dépassé la simple demande (« Demande »
 * exclu : rien n'est vendu tant que ce n'est pas confirmé) et qui n'ont pas été annulés ni
 * transformés en no-show. La période filtre sur la date d'arrivée, comme le reste du back
 * office (CdC § 6.8, filtre « du … au … »).
 */
final class EtatsPilotage
{
    /** États qui comptent comme une vente pour le chiffre d'affaires. */
    private const ETATS_VENDUS = [
        EtatDuSejour::Confirme, EtatDuSejour::Arrive, EtatDuSejour::Parti, EtatDuSejour::Cloture,
    ];

    /** Chiffre d'affaires détaillé, ligne par ligne : séjours puis transferts (CdC § 9.1). */
    public function caDetaille(?Carbon $du, ?Carbon $au, ?int $residenceId = null): array
    {
        $sejours = $this->sejoursVendus($du, $au, $residenceId);

        $lignesSejours = $sejours->map(fn (Sejour $s): array => [
            'type' => 'sejour',
            'reference' => $s->reference,
            'residence' => $s->logement->residence->nom,
            'logement' => $s->logement->nom,
            'arrivee' => $s->arrivee->format('Y-m-d'),
            'depart' => $s->depart->format('Y-m-d'),
            'canal' => $s->canal,
            'total_ht' => (int) ($s->devis['total_ht'] ?? 0),
            'total_tva' => (int) ($s->devis['total_tva'] ?? 0),
            'autres_taxes' => (int) ($s->devis['autres_taxes'] ?? 0),
            'net_a_payer' => $s->net_a_payer,
        ])->values();

        $transferts = Transfert::query()->with('sejour')
            ->when($du, fn (Builder $q) => $q->whereDate('date_heure_prevue', '>=', $du))
            ->when($au, fn (Builder $q) => $q->whereDate('date_heure_prevue', '<=', $au))
            ->when($residenceId, fn (Builder $q) => $q->whereHas('sejour.logement', fn (Builder $l) => $l->where('residence_id', $residenceId)))
            ->get();

        $lignesTransferts = $transferts->map(fn (Transfert $t): array => [
            'type' => 'transfert',
            'reference' => $t->reference,
            'sejour' => $t->sejour?->reference,
            'date' => $t->date_heure_prevue->format('Y-m-d'),
            'montant' => $t->montant,
        ])->values();

        return [
            'lignes_sejours' => $lignesSejours->all(),
            'lignes_transferts' => $lignesTransferts->all(),
            'totaux' => [
                'total_ht' => (int) $lignesSejours->sum('total_ht'),
                'total_tva' => (int) $lignesSejours->sum('total_tva'),
                'autres_taxes' => (int) $lignesSejours->sum('autres_taxes'),
                'net_a_payer_sejours' => (int) $lignesSejours->sum('net_a_payer'),
                'total_transferts' => (int) $lignesTransferts->sum('montant'),
            ],
        ];
    }

    /** Ventes regroupées par résidence puis par type de logement (CdC § 9.1). */
    public function caParResidenceEtType(?Carbon $du, ?Carbon $au): array
    {
        $sejours = $this->sejoursVendus($du, $au, null);

        $parResidence = $sejours->groupBy(fn (Sejour $s) => $s->logement->residence->nom)
            ->map(fn (Collection $groupe, string $residence): array => [
                'residence' => $residence,
                'nombre_sejours' => $groupe->count(),
                'nuitees' => (int) $groupe->sum(fn (Sejour $s) => $s->nombreDeNuits()),
                'net_a_payer' => (int) $groupe->sum('net_a_payer'),
            ])->values();

        $parType = $sejours->groupBy(fn (Sejour $s) => $s->logement->type->nom ?? 'Non renseigné')
            ->map(fn (Collection $groupe, string $type): array => [
                'type_logement' => $type,
                'nombre_sejours' => $groupe->count(),
                'nuitees' => (int) $groupe->sum(fn (Sejour $s) => $s->nombreDeNuits()),
                'net_a_payer' => (int) $groupe->sum('net_a_payer'),
            ])->values();

        return ['par_residence' => $parResidence->all(), 'par_type_logement' => $parType->all()];
    }

    /**
     * Taux d'occupation, RevPAR et prix moyen, par résidence (CdC § 6.2, § 9.1).
     *
     * Simplification assumée (aucun calcul RevPAR n'existait déjà dans le code à la date
     * d'écriture) : les nuitées vendues sont celles des séjours dont la date d'ARRIVÉE tombe
     * dans la période — pas un calcul au prorata des nuits qui chevauchent les bornes. Les
     * nuitées disponibles comptent tous les logements existants (bloqués ou non) sur le
     * nombre de jours de la période. RevPAR = chiffre d'affaires hébergement ÷ nuitées
     * disponibles (CdC § 9.1, glossaire).
     */
    public function occupationEtRevpar(Carbon $du, Carbon $au, ?int $residenceId = null): array
    {
        $jours = max(1, (int) $du->diffInDays($au) + 1);
        $sejours = $this->sejoursVendus($du, $au, $residenceId);

        $logements = Logement::query()
            ->when($residenceId, fn (Builder $q) => $q->where('residence_id', $residenceId))
            ->get(['id', 'residence_id']);

        return $logements->groupBy('residence_id')->map(function (Collection $logementsDeLaResidence) use ($sejours, $jours): array {
            $residenceId = $logementsDeLaResidence->first()->residence_id;
            $residence = Residence::find($residenceId);
            $sejoursDeLaResidence = $sejours->filter(fn (Sejour $s) => $s->logement->residence_id === $residenceId);

            $nuiteesDisponibles = $logementsDeLaResidence->count() * $jours;
            $nuiteesVendues = (int) $sejoursDeLaResidence->sum(fn (Sejour $s) => $s->nombreDeNuits());
            $caHebergement = (int) $sejoursDeLaResidence->sum(fn (Sejour $s) => (int) ($s->devis['hebergement_net_ht'] ?? 0));

            return [
                'residence' => $residence?->nom,
                'nuitees_disponibles' => $nuiteesDisponibles,
                'nuitees_vendues' => $nuiteesVendues,
                'taux_occupation' => $nuiteesDisponibles > 0 ? round($nuiteesVendues / $nuiteesDisponibles, 4) : 0.0,
                'revpar' => $nuiteesDisponibles > 0 ? (int) round($caHebergement / $nuiteesDisponibles) : 0,
                'prix_moyen' => $nuiteesVendues > 0 ? (int) round($caHebergement / $nuiteesVendues) : 0,
            ];
        })->values()->all();
    }

    /** Volume, montants retenus et motifs des annulations et no-show (CdC § 9.1). */
    public function annulationsEtNoShow(Carbon $du, Carbon $au): array
    {
        $annulations = Sejour::query()->where('etat', EtatDuSejour::Annule)
            ->whereBetween('annule_le', [$du, $au])->get();
        $noShow = Sejour::query()->where('etat', EtatDuSejour::NoShow)
            ->whereBetween('no_show_le', [$du, $au])->get();

        return [
            'annulations' => [
                'volume' => $annulations->count(),
                'montant_retenu' => (int) $annulations->sum('montant_retenu_annulation'),
                'par_motif' => $annulations->groupBy(fn (Sejour $s) => $s->motif_annulation ?? 'Non renseigné')
                    ->map(fn (Collection $g, string $motif): array => ['motif' => $motif, 'volume' => $g->count()])->values()->all(),
            ],
            'no_show' => [
                'volume' => $noShow->count(),
                'montant_retenu' => (int) $noShow->sum('montant_retenu_annulation'),
            ],
        ];
    }

    /** Résidences disponibles / occupées, par commune et par quartier (CdC § 9.1). */
    public function disponibiliteResidences(): array
    {
        return Residence::query()->with('quartier.commune')->get()
            ->map(fn (Residence $r): array => [
                'residence' => $r->nom,
                'commune' => $r->quartier?->commune?->nom,
                'quartier' => $r->quartier?->nom,
                'disponibilite' => $r->disponibilite->value,
                // « Occupée » (fermée par le propriétaire) : durée de la fermeture en cours, depuis
                // le dernier changement d'état — approximation faute d'historique dédié des bascules.
                'jours_fermee' => $r->disponibilite === Disponibilite::Occupee ? (int) $r->updated_at->diffInDays(now()) : null,
                'reouverture_prevue_le' => $r->reouverture_prevue_le?->format('Y-m-d'),
            ])->all();
    }

    /**
     * Marge par résidence : prix de vente − coût propriétaire (CdC § 9.1, § 9.3).
     *
     * Le coût retenu est `prix_proprietaire_par_nuit`, figé sur chaque séjour à la réservation
     * (CdC § 5.4) — la SEULE donnée de coût propriétaire déjà posée dans le code à ce jour.
     * Le calcul de reversement propriétaire complet (P3-PRO-02, charges refacturées, cautions)
     * n'existe pas encore : cette marge est donc une approximation qui se précisera une fois
     * ce calcul disponible, sans changer la forme de la réponse.
     */
    public function margeParResidence(?Carbon $du, ?Carbon $au): array
    {
        $sejours = $this->sejoursVendus($du, $au, null);

        return $sejours->groupBy(fn (Sejour $s) => $s->logement->residence->nom)
            ->map(function (Collection $groupe, string $residence): array {
                $prixVente = (int) $groupe->sum(fn (Sejour $s) => (int) ($s->devis['hebergement_net_ht'] ?? 0));
                $coutProprietaire = (int) $groupe->sum(fn (Sejour $s) => (int) ($s->prix_proprietaire_par_nuit ?? 0) * $s->nombreDeNuits());

                return [
                    'residence' => $residence,
                    'prix_de_vente_ht' => $prixVente,
                    'cout_proprietaire' => $coutProprietaire,
                    'marge' => $prixVente - $coutProprietaire,
                ];
            })->values()->all();
    }

    /** Réservations confirmées à venir sur 90 jours : nuitées vendues, revenu attendu (CdC § 9.1). */
    public function previsionnel90Jours(): array
    {
        $aujourdHui = Carbon::today();
        $fin = $aujourdHui->copy()->addDays(90);

        $sejours = Sejour::query()->with('logement.residence')
            ->whereIn('etat', [EtatDuSejour::Confirme, EtatDuSejour::Arrive])
            ->whereBetween('arrivee', [$aujourdHui, $fin])
            ->get();

        $parSemaine = $sejours->groupBy(fn (Sejour $s) => $s->arrivee->startOfWeek()->format('Y-m-d'))
            ->map(fn (Collection $g, string $semaine): array => [
                'semaine_du' => $semaine,
                'nombre_sejours' => $g->count(),
                'nuitees' => (int) $g->sum(fn (Sejour $s) => $s->nombreDeNuits()),
                'revenu_attendu' => (int) $g->sum('net_a_payer'),
            ])->sortBy('semaine_du')->values();

        return [
            'du' => $aujourdHui->format('Y-m-d'),
            'au' => $fin->format('Y-m-d'),
            'nombre_sejours' => $sejours->count(),
            'nuitees_vendues' => (int) $sejours->sum(fn (Sejour $s) => $s->nombreDeNuits()),
            'revenu_attendu' => (int) $sejours->sum('net_a_payer'),
            'par_semaine' => $parSemaine->all(),
        ];
    }

    /** @return Collection<int, Sejour> */
    private function sejoursVendus(?Carbon $du, ?Carbon $au, ?int $residenceId): Collection
    {
        return Sejour::query()->with('logement.residence', 'logement.type')
            ->whereIn('etat', self::ETATS_VENDUS)
            ->when($du, fn (Builder $q) => $q->whereDate('arrivee', '>=', $du))
            ->when($au, fn (Builder $q) => $q->whereDate('arrivee', '<=', $au))
            ->when($residenceId, fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->where('residence_id', $residenceId)))
            ->get();
    }
}
