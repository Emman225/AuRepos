<?php

namespace App\Domain\Caisse\Services;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Enums\Guichet;
use App\Domain\Caisse\Enums\ModeDeReglement;
use App\Domain\Caisse\Models\AvanceClient;
use App\Domain\Caisse\Models\Reglement;
use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Sejour;
use Illuminate\Support\Facades\DB;

/**
 * Avances clients (CdC § 4) : « Un client peut déposer au guichet une somme sans réservation.
 * Ses séjours suivants réglés hors ligne s'en déduisent d'eux-mêmes, du dépôt le plus ancien
 * au plus récent. Une avance ne se rembourse pas : elle s'utilise. »
 *
 * Repris de Mon Gravier (`app/Services/Avances.php`).
 */
final class Avances
{
    public function __construct(
        private readonly SoldeDesSejours $soldes,
        private readonly Numerotation $numerotation,
        private readonly JournalAudit $journal,
    ) {}

    public function disponible(User|int $client): int
    {
        return (int) AvanceClient::query()->where('client_id', $client instanceof User ? $client->id : $client)->sum('solde');
    }

    /**
     * Un règlement vient d'être EFFECTUÉ : s'il apporte de l'argent sans affaire en face (dépôt d'avance,
     * ou surplus que le caissier a choisi de garder en avance), cet argent devient une avance.
     * À appeler dans la transaction de finalisation.
     */
    public function constituerDepuis(Reglement $reglement): ?AvanceClient
    {
        // Un dépôt de caution (P2-CAU-01) n'a jamais d'imputation en face (la caution n'entre pas
        // dans le reste dû du séjour) : sans cette garde, il serait pris pour un surplus « sans
        // affaire » et versé en avance client, ce qu'il n'est pas — il reste dû au client, détenu.
        if (! $reglement->estUnEncaissement() || $reglement->mode === ModeDeReglement::Avance || $reglement->guichet === Guichet::Cautions) {
            return null;
        }

        $sansAffaire = $reglement->montant - (int) $reglement->imputations()->sum('montant');
        if ($sansAffaire <= 0) {
            return null;
        }

        return AvanceClient::create([
            'client_id' => $reglement->tiers_id, 'reglement_id' => $reglement->id, 'montant' => $sansAffaire, 'solde' => $sansAffaire,
        ]);
    }

    /**
     * Déduit l'avance disponible du reste dû d'un séjour, du dépôt le plus ancien au plus récent.
     * Le règlement « AV » naît EFFECTUÉ : l'argent a déjà passé le circuit de preuve, au dépôt.
     * À appeler dans la transaction de la réservation. Rend le montant imputé.
     */
    public function imputerSur(Sejour $sejour, User $declencheur): int
    {
        if ($sejour->client_id === null) {
            return 0;
        }

        return DB::transaction(function () use ($sejour, $declencheur): int {
            // Verrou : deux réservations simultanées du même client ne consomment pas deux fois la même avance.
            $avances = AvanceClient::query()->with('reglement')->where('client_id', $sejour->client_id)->where('solde', '>', 0)
                ->orderBy('id')->lockForUpdate()->get();

            $aCouvrir = min($this->soldes->de($sejour)['reste_du'], (int) $avances->sum('solde'));
            if ($aCouvrir <= 0) {
                return 0;
            }

            $premiere = $avances->first();
            $reglement = Reglement::create([
                'sens' => 'encaissement', 'guichet' => Guichet::Sejours, 'agence_id' => $premiere->reglement->agence_id, 'tiers_id' => $sejour->client_id,
                'montant' => $aCouvrir, 'mode' => ModeDeReglement::Avance, 'etat' => EtatDuReglement::Effectue,
                'notes' => 'Imputation automatique de l’avance du client sur '.$sejour->libelleAudit().'.',
                'saisi_par' => $declencheur->id, 'saisi_le' => now(), 'finalise_le' => now(),
                // AV-AAAA-NNN : même compteur que tous les reçus de la caisse (CdC § 8.4).
                'numero_recu' => sprintf('AV-%d-%03d', now()->year, $this->numerotation->suivant('recus', now()->year)),
            ]);
            $reglement->imputations()->create(['affaire_type' => $sejour->getMorphClass(), 'affaire_id' => $sejour->id, 'montant' => $aCouvrir]);

            $reste = $aCouvrir;
            foreach ($avances as $avance) {
                $part = min($reste, $avance->solde);
                if ($part <= 0) {
                    break;
                }
                $avance->decrement('solde', $part);
                DB::table('mouvements_avance')->insert(['avance_id' => $avance->id, 'reglement_id' => $reglement->id, 'montant' => -$part, 'created_at' => now(), 'updated_at' => now()]);
                $reste -= $part;
            }

            $this->journal->consigner('avance_imputee', 'Avance imputée : '.number_format($aCouvrir, 0, ',', ' ').' F sur '.$sejour->libelleAudit().'.', $reglement, auteur: $declencheur);

            return $aCouvrir;
        });
    }

    /**
     * Une DEMANDE annulée (ou expirée) rend au client l'avance qu'elle avait consommée : rien n'a été
     * servi, et une avance « s'utilise », elle ne se perd pas. Le numéro AV reste attribué, marqué rejeté.
     */
    public function recrediterPour(Sejour $sejour, string $motif): int
    {
        return DB::transaction(function () use ($sejour, $motif): int {
            $reglements = Reglement::query()->where('mode', ModeDeReglement::Avance)->where('etat', EtatDuReglement::Effectue)
                ->whereHas('imputations', fn ($q) => $q->where('affaire_type', $sejour->getMorphClass())->where('affaire_id', $sejour->id))->get();

            $total = 0;
            foreach ($reglements as $reglement) {
                foreach (DB::table('mouvements_avance')->where('reglement_id', $reglement->id)->where('montant', '<', 0)->get() as $mouvement) {
                    AvanceClient::whereKey($mouvement->avance_id)->lockForUpdate()->first()?->increment('solde', -$mouvement->montant);
                    DB::table('mouvements_avance')->insert(['avance_id' => $mouvement->avance_id, 'reglement_id' => $reglement->id, 'montant' => -$mouvement->montant, 'created_at' => now(), 'updated_at' => now()]);
                    $total += -$mouvement->montant;
                }
                $reglement->update(['etat' => EtatDuReglement::Rejete, 'rejete_le' => now(), 'motif_rejet' => $motif]);
            }

            if ($total > 0) {
                $this->journal->consigner('avance_recreditee', 'Avance recréditée : '.number_format($total, 0, ',', ' ')." F ({$motif}).", $sejour);
            }

            return $total;
        });
    }
}
