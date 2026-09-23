<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Comptes\Enums\Profil;
use App\Domain\Partenaires\Models\Apporteur;
use App\Domain\Partenaires\Models\CommissionApporteur;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Enums\StatutDemandeATerme;
use App\Domain\Sejours\Models\Client;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Sejours\Services\ComptesATerme;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Créances et dettes (CdC § 9.1, § 9.2) : ce que les clients doivent, leur ancienneté,
 * l'activité des filleuls d'un apporteur, et la file de relance. Tout est lu depuis les
 * mécanismes déjà en place — `ComptesATerme::encours()` et `SoldeDesSejours` restent les
 * SEULES sources de calcul du reste dû, rien n'est recalculé ici.
 */
final class CreancesEtDettes
{
    public function __construct(
        private readonly ComptesATerme $comptesATerme,
        private readonly SoldeDesSejours $soldes,
    ) {}

    /** Situation des comptes à terme acceptés : plafond, encours, disponible (CdC § 9.2). */
    public function etatClientATerme(): array
    {
        return Client::query()->with('utilisateur')
            ->where('statut_a_terme', StatutDemandeATerme::Acceptee)
            ->get()
            ->map(function (Client $client): array {
                $encours = $this->comptesATerme->encours($client);

                return [
                    'client' => $client->utilisateur->nomComplet(),
                    'raison_sociale' => $client->raison_sociale,
                    'plafond_credit' => $client->plafond_credit,
                    'encours' => $encours,
                    // 0 = aucune limite (CdC § 5.1).
                    'disponible' => $client->plafond_credit > 0 ? max(0, $client->plafond_credit - $encours) : null,
                ];
            })->values()->all();
    }

    /**
     * Balance âgée : reste dû par séjour actif, réparti par ancienneté depuis la date de
     * départ (CdC § 9.1). Bornes : 0-30, 31-60, 61-90, plus de 90 jours.
     */
    public function balanceAgee(?Carbon $asOf = null): array
    {
        $asOf = $asOf ?? Carbon::today();

        $sejours = Sejour::query()->with('client', 'logement')
            ->whereNotIn('etat', [EtatDuSejour::Annule, EtatDuSejour::NoShow, EtatDuSejour::Demande])
            ->get();

        $lignes = $sejours->map(function (Sejour $sejour) use ($asOf): ?array {
            $solde = $this->soldes->de($sejour);
            if ($solde['reste_du'] <= 0) {
                return null;
            }
            $anciennete = $sejour->depart->lessThanOrEqualTo($asOf) ? (int) $sejour->depart->diffInDays($asOf) : 0;

            return [
                'sejour' => $sejour->reference,
                'client' => $sejour->client?->nomComplet(),
                'depart' => $sejour->depart->format('Y-m-d'),
                'reste_du' => $solde['reste_du'],
                'anciennete_jours' => $anciennete,
                'tranche' => match (true) {
                    $anciennete <= 30 => '0-30',
                    $anciennete <= 60 => '31-60',
                    $anciennete <= 90 => '61-90',
                    default => 'plus_90',
                },
            ];
        })->filter()->values();

        $parTranche = $lignes->groupBy('tranche')->map(fn (Collection $g, string $tranche): array => [
            'tranche' => $tranche, 'nombre' => $g->count(), 'montant' => (int) $g->sum('reste_du'),
        ])->values();

        return ['lignes' => $lignes->all(), 'par_tranche' => $parTranche->all(), 'total_reste_du' => (int) $lignes->sum('reste_du')];
    }

    /** Récapitulatif global : ce que les clients doivent, comptant et à terme (CdC § 9.1). */
    public function recapitulatifCreances(): array
    {
        $balance = $this->balanceAgee();
        $sejours = Sejour::query()
            ->whereNotIn('etat', [EtatDuSejour::Annule, EtatDuSejour::NoShow, EtatDuSejour::Demande])->get();

        $aTermeIds = Client::query()->where('statut_a_terme', StatutDemandeATerme::Acceptee)->pluck('user_id')->all();

        $comptant = 0;
        $aTerme = 0;
        foreach ($sejours as $sejour) {
            $reste = $this->soldes->de($sejour)['reste_du'];
            if ($reste <= 0) {
                continue;
            }
            if (in_array($sejour->client_id, $aTermeIds, true)) {
                $aTerme += $reste;
            } else {
                $comptant += $reste;
            }
        }

        return [
            'total' => $balance['total_reste_du'],
            'comptant' => $comptant,
            'a_terme' => $aTerme,
        ];
    }

    /**
     * Récapitulatif global : ce que l'entreprise doit à ses tiers (CdC § 9.1).
     *
     * Approximation assumée : faute de ledger de reversement dédié (P3-PRO-04 « demandes de
     * paiement » n'existe pas encore), la dette envers un partenaire ne peut se lire QUE via
     * les décaissements déjà SAISIS mais pas encore FINALISÉS (guichet « dettes partenaires »,
     * circuit de preuve en cours) et via les commissions d'apporteur non couvertes par un
     * décaissement finalisé — ce sont les deux seuls montants déjà posés dans le code.
     */
    public function recapitulatifDettes(): array
    {
        $enCours = Reglement::query()
            ->where('sens', 'decaissement')->where('guichet', Guichet::DettesPartenaires)
            ->get()->filter(fn (Reglement $r) => $r->etat->enCours());

        $verseParTiers = Reglement::query()
            ->where('sens', 'decaissement')->where('guichet', Guichet::DettesPartenaires)
            ->where('etat', EtatDuReglement::Effectue)
            ->selectRaw('tiers_id, sum(montant) as total')->groupBy('tiers_id')->pluck('total', 'tiers_id');

        $commissionsDues = CommissionApporteur::query()->with('apporteur')->get()
            ->groupBy('apporteur_id')
            ->sum(fn (Collection $g) => max(0, $g->sum('montant') - (int) ($verseParTiers[$g->first()->apporteur->user_id] ?? 0)));

        return [
            'decaissements_en_cours' => [
                'nombre' => $enCours->count(),
                'montant' => (int) $enCours->sum('montant'),
            ],
            'commissions_apporteurs_non_versees' => (int) $commissionsDues,
        ];
    }

    /** Activité des clients parrainés par chaque apporteur (CdC § 9.1, § 9.2). */
    public function etatPaiementFilleul(?Carbon $du, ?Carbon $au): array
    {
        return Apporteur::query()->with('utilisateur', 'filleuls')->get()
            ->map(function (Apporteur $apporteur) use ($du, $au): array {
                $commissions = CommissionApporteur::query()->where('apporteur_id', $apporteur->id)
                    ->when($du, fn ($q) => $q->whereDate('created_at', '>=', $du))
                    ->when($au, fn ($q) => $q->whereDate('created_at', '<=', $au))
                    ->get();

                return [
                    'apporteur' => $apporteur->nomAffiche(),
                    'code' => $apporteur->code,
                    'nombre_filleuls' => $apporteur->filleuls->count(),
                    'nombre_commissions' => $commissions->count(),
                    'montant_commissions' => (int) $commissions->sum('montant'),
                ];
            })->values()->all();
    }

    /**
     * File de relance : comptes dont le reste dû dépasse le seuil paramétré, au-delà du délai
     * paramétré depuis le départ (CdC § 9.1 « délai et seuil paramétrables »). Aucun envoi
     * automatique ici — seulement la liste, l'envoi reste une action manuelle du back office.
     */
    public function relances(int $delaiJours, int $seuilMontant): array
    {
        $seuilDate = Carbon::today()->subDays($delaiJours);
        $balance = $this->balanceAgee();

        $lignes = collect($balance['lignes'])
            ->filter(fn (array $l) => $l['reste_du'] >= $seuilMontant)
            ->filter(fn (array $l) => Carbon::parse($l['depart'])->lessThanOrEqualTo($seuilDate))
            ->values();

        return [
            'delai_jours' => $delaiJours,
            'seuil_montant' => $seuilMontant,
            'lignes' => $lignes->all(),
            'total' => (int) $lignes->sum('reste_du'),
        ];
    }
}
