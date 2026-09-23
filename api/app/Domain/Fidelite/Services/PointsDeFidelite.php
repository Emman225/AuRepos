<?php

namespace App\Domain\Fidelite\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Fidelite\Models\MouvementPoints;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Sejour;
use App\Support\Api\ErreurMetier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Points de fidélité (CdC § 4) :
 *   1 000 F encaissés = 1 point · 1 point = 10 F (paramétrable) · résultat TRONQUÉ ·
 *   points repris à l'annulation remboursée · minimum à payer respecté.
 *
 * Le solde n'est jamais stocké : c'est la somme des mouvements. Impossible qu'un total
 * dérive de son détail, et le relevé du client s'explique ligne par ligne.
 */
final class PointsDeFidelite
{
    public function __construct(
        private readonly Parametres $parametres,
        private readonly JournalAudit $journal,
    ) {}

    public function solde(User|int $client): int
    {
        return (int) MouvementPoints::query()->where('client_id', $client instanceof User ? $client->id : $client)->sum('points');
    }

    /** Valeur en francs du solde, au barème d'aujourd'hui. */
    public function valeurDuSolde(User|int $client): int
    {
        return $this->solde($client) * $this->valeurDuPoint();
    }

    /**
     * Un règlement vient d'être EFFECTUÉ : le client gagne des points. À appeler dans la transaction
     * de finalisation. Une imputation d'avance n'en donne pas : l'argent a déjà donné ses points au dépôt.
     */
    public function acquerirPour(Reglement $reglement): int
    {
        if (! $reglement->estUnEncaissement() || $reglement->mode === ModeDeReglement::Avance) {
            return 0;
        }

        $parPoint = $this->montantParPoint();
        // Tronqué : 2 999 F encaissés donnent 2 points, pas 3.
        $points = intdiv($reglement->montant, $parPoint);
        if ($points <= 0) {
            return 0;
        }

        $this->inscrire($reglement->tiers_id, 'acquisition', $points, 'Règlement '.($reglement->numero_recu ?? $reglement->reference), $reglement);

        return $points;
    }

    /**
     * Ce que le client peut utiliser sur un séjour : ni plus que son solde, ni assez pour faire
     * descendre le net sous le minimum à payer. Rend les points utilisables et leur valeur.
     *
     * @return array{solde: int, utilisables: int, valeur: int, valeur_du_point: int, plafonne: bool}
     */
    public function utilisablesSur(User|int $client, int $baseHt, int $demandes): array
    {
        $solde = $this->solde($client);
        $valeurDuPoint = $this->valeurDuPoint();
        $demandes = max(0, min($demandes, $solde));

        // Plafond en francs : ce qu'on peut retirer du HT sans passer sous le minimum à payer.
        $plafondEnFrancs = max(0, $baseHt - $this->plancherHt());
        $utilisables = $valeurDuPoint > 0 ? min($demandes, intdiv($plafondEnFrancs, $valeurDuPoint)) : 0;

        return [
            'solde' => $solde,
            'utilisables' => $utilisables,
            'valeur' => $utilisables * $valeurDuPoint,
            'valeur_du_point' => $valeurDuPoint,
            'plafonne' => $utilisables < $demandes,
        ];
    }

    /** Le client consomme ses points sur un séjour. À appeler dans la transaction de la réservation. */
    public function utiliserSur(Sejour $sejour, int $points): int
    {
        if ($points <= 0 || $sejour->client_id === null) {
            return 0;
        }

        return DB::transaction(function () use ($sejour, $points): int {
            // Verrou : deux réservations simultanées ne consomment pas deux fois les mêmes points.
            MouvementPoints::query()->where('client_id', $sejour->client_id)->lockForUpdate()->get();

            if ($points > $this->solde($sejour->client_id)) {
                throw new ErreurMetier('Vous n’avez pas assez de points de fidélité.', 'points_insuffisants', 422);
            }

            $this->inscrire($sejour->client_id, 'utilisation', -$points, 'Séjour '.$sejour->reference, $sejour);

            return $points;
        });
    }

    /**
     * « Points repris à l'annulation remboursée » (CdC § 4) : le client récupère ceux qu'il avait
     * utilisés, et rend ceux que son règlement remboursé lui avait donnés.
     */
    public function reprendreSur(Sejour $sejour, string $motif): void
    {
        DB::transaction(function () use ($sejour, $motif): void {
            $utilises = (int) MouvementPoints::query()
                ->where('origine_type', $sejour->getMorphClass())->where('origine_id', $sejour->id)
                ->where('nature', 'utilisation')->sum('points');

            if ($utilises < 0) {
                $this->inscrire($sejour->client_id, 'restitution', -$utilises, "Séjour {$sejour->reference} annulé — {$motif}", $sejour);
            }

            // Les points gagnés sur les règlements de CE séjour sont repris, sans jamais rendre le solde négatif.
            $gagnes = (int) MouvementPoints::query()
                ->where('client_id', $sejour->client_id)->where('nature', 'acquisition')
                ->whereIn('origine_id', fn ($q) => $q->select('reglement_id')->from('imputations_reglement')
                    ->where('affaire_type', $sejour->getMorphClass())->where('affaire_id', $sejour->id))
                ->where('origine_type', (new Reglement)->getMorphClass())
                ->sum('points');

            $reprenables = min($gagnes, max(0, $this->solde($sejour->client_id)));
            if ($reprenables > 0) {
                $this->inscrire($sejour->client_id, 'reprise', -$reprenables, "Séjour {$sejour->reference} annulé — {$motif}", $sejour);
            }
        });
    }

    /**
     * Relevé du client : chaque ligne s'explique.
     *
     * @return array{solde: int, valeur: int, valeur_du_point: int, montant_par_point: int, mouvements: list<array<string, mixed>>}
     */
    public function releve(User|int $client): array
    {
        $id = $client instanceof User ? $client->id : $client;
        $mouvements = MouvementPoints::query()->where('client_id', $id)->orderByDesc('id')->limit(100)->get();

        return [
            'solde' => $this->solde($id),
            'valeur' => $this->valeurDuSolde($id),
            'valeur_du_point' => $this->valeurDuPoint(),
            'montant_par_point' => $this->montantParPoint(),
            'mouvements' => $mouvements->map(fn (MouvementPoints $m): array => [
                'date' => $m->created_at?->format('d/m/Y H:i:s'),
                'nature' => $m->nature,
                'points' => $m->points,
                'libelle' => $m->libelle,
                'valeur' => $m->valeurEnFrancs(),
            ])->all(),
        ];
    }

    private function inscrire(?int $clientId, string $nature, int $points, string $libelle, ?Model $origine = null): void
    {
        if ($clientId === null) {
            return;
        }

        $mouvement = MouvementPoints::create([
            'client_id' => $clientId, 'nature' => $nature, 'points' => $points, 'libelle' => $libelle,
            'origine_type' => $origine?->getMorphClass(), 'origine_id' => $origine?->getKey(),
            // Barème figé sur le mouvement : un changement de paramètre ne réécrit pas l'historique.
            'montant_par_point' => $this->montantParPoint(), 'valeur_du_point' => $this->valeurDuPoint(),
        ]);

        $this->journal->consigner('points_'.$nature, sprintf(
            '%s de %d point(s) : %s.', ucfirst($nature), abs($points), $libelle,
        ), $mouvement);
    }

    private function montantParPoint(): int
    {
        return max(1, (int) $this->parametres->valeur('general.fidelite_montant_par_point'));
    }

    private function valeurDuPoint(): int
    {
        return max(0, (int) $this->parametres->valeur('general.fidelite_valeur_du_point'));
    }

    /** Hébergement HT en dessous duquel une réduction ne peut pas faire descendre le séjour. */
    private function plancherHt(): int
    {
        $tva = (float) $this->parametres->valeur('taxes.tva');
        $tdt = (float) $this->parametres->valeur('taxes.tdt');
        $minimum = (int) $this->parametres->valeur('general.minimum_a_payer');

        return (int) ceil($minimum / ((1 + $tva / 100) * (1 + $tdt / 100)));
    }
}
