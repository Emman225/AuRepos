<?php

namespace App\Domain\Comptabilite\Services;

use App\Domain\Caisse\Services\SoldeDesSejours;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * TVA collectée, taxe de développement touristique (TDT) et taxe de séjour (CdC § 9.3).
 *
 * Chaque montant est lu tel quel dans `Sejour::devis` — le devis FIGÉ à la réservation
 * (« taux gelés à la réservation », CdC § 5.4) — jamais recalculé au taux courant des
 * paramètres. « Encaissé au prorata » applique le taux d'encaissement du séjour
 * (`SoldeDesSejours::de()`) au montant facturé de la taxe, comme demandé au § 9.3
 * (« TVA encaissée au prorata du règlement »).
 */
final class EtatsFiscaux
{
    public function __construct(private readonly SoldeDesSejours $soldes) {}

    /** TVA facturée, encaissée au prorata, reste à encaisser (CdC § 9.3). */
    public function tva(Carbon $du, Carbon $au, ?int $residenceId = null): array
    {
        return $this->etat($du, $au, $residenceId, fn (array $devis): int => (int) ($devis['total_tva'] ?? 0));
    }

    /** Jumeau de l'état de TVA pour la taxe de développement touristique (CdC § 9.3). */
    public function tdt(Carbon $du, Carbon $au, ?int $residenceId = null): array
    {
        return $this->etat($du, $au, $residenceId, fn (array $devis): int => (int) ($devis['tdt'] ?? 0));
    }

    /** Taxe de séjour collectée, par résidence et par mois (CdC § 9.3). */
    public function taxeDeSejour(Carbon $du, Carbon $au): array
    {
        $sejours = $this->sejoursFactures($du, $au, null);

        $parResidenceEtMois = $sejours->groupBy(fn (Sejour $s) => $s->logement->residence->nom.'|'.$s->arrivee->format('Y-m'))
            ->map(function (Collection $g) {
                /** @var Sejour $premier */
                $premier = $g->first();
                [$residence, $mois] = [$premier->logement->residence->nom, $premier->arrivee->format('Y-m')];

                return [
                    'residence' => $residence,
                    'mois' => $mois,
                    'nombre_sejours' => $g->count(),
                    'taxe_de_sejour' => (int) $g->sum(fn (Sejour $s) => (int) ($s->devis['taxe_de_sejour'] ?? 0)),
                ];
            })->values();

        return [
            'lignes' => $parResidenceEtMois->all(),
            'total' => (int) $sejours->sum(fn (Sejour $s) => (int) ($s->devis['taxe_de_sejour'] ?? 0)),
        ];
    }

    /** @param  callable(array<string, mixed>): int  $montantFacture */
    private function etat(Carbon $du, Carbon $au, ?int $residenceId, callable $montantFacture): array
    {
        $sejours = $this->sejoursFactures($du, $au, $residenceId);

        $lignes = $sejours->map(function (Sejour $sejour) use ($montantFacture): array {
            $devis = $sejour->devis ?? [];
            $facture = $montantFacture($devis);
            $netAPayer = max(1, $sejour->net_a_payer);
            $solde = $this->soldes->de($sejour);
            $tauxEncaisse = min(1.0, $solde['encaisse'] / $netAPayer);
            $encaisseAuProrata = (int) round($facture * $tauxEncaisse);

            return [
                'sejour' => $sejour->reference,
                'residence' => $sejour->logement->residence->nom,
                'mois' => $sejour->arrivee->format('Y-m'),
                'base_ht' => (int) ($devis['total_ht'] ?? 0),
                'facture' => $facture,
                'encaisse_au_prorata' => $encaisseAuProrata,
                'reste_a_encaisser' => max(0, $facture - $encaisseAuProrata),
            ];
        })->values();

        $parMois = $lignes->groupBy('mois')->map(fn (Collection $g, string $mois): array => [
            'mois' => $mois,
            'facture' => (int) $g->sum('facture'),
            'encaisse_au_prorata' => (int) $g->sum('encaisse_au_prorata'),
            'reste_a_encaisser' => (int) $g->sum('reste_a_encaisser'),
        ])->values();

        return [
            'lignes' => $lignes->all(),
            'par_mois' => $parMois->all(),
            'totaux' => [
                'facture' => (int) $lignes->sum('facture'),
                'encaisse_au_prorata' => (int) $lignes->sum('encaisse_au_prorata'),
                'reste_a_encaisser' => (int) $lignes->sum('reste_a_encaisser'),
            ],
        ];
    }

    /** @return Collection<int, Sejour> */
    private function sejoursFactures(Carbon $du, Carbon $au, ?int $residenceId): Collection
    {
        return Sejour::query()->with('logement.residence')
            ->whereNotIn('etat', [EtatDuSejour::Demande, EtatDuSejour::Annule, EtatDuSejour::NoShow])
            ->whereDate('arrivee', '>=', $du)->whereDate('arrivee', '<=', $au)
            ->when($residenceId, fn (Builder $q) => $q->whereHas('logement', fn (Builder $l) => $l->where('residence_id', $residenceId)))
            ->get();
    }
}
