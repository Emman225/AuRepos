<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Comptes\Models\User;
use App\Domain\Exploitation\Services\Missions;
use App\Domain\Fiscalite\Enums\TypeDeFacture;
use App\Domain\Fiscalite\Models\Facture;
use App\Domain\Fiscalite\Services\Factures;
use App\Domain\Repas\Enums\EtatDeCommande;
use App\Domain\Sejours\Enums\EtatDuSejour;
use App\Domain\Sejours\Models\Sejour;
use App\Domain\Transferts\Enums\EtatDuTransfert;
use App\Support\Api\ErreurMetier;
use Illuminate\Support\Facades\DB;

/**
 * Check-out (P2-SEJ-04, CdC § 6.3) : consommations déjà connues (extras, transferts, repas —
 * additionnées via les relations existantes, rien n'est recalculé), décision sur la caution
 * (retenue TOUJOURS saisie manuellement et motivée — jamais une formule de dégât automatique,
 * même choix que la rémunération d'un chauffeur ou d'un livreur dans ce projet), et facture
 * UNIQUE du séjour via le mécanisme déjà en place (proforma → facture, CdC § 9.4). Déclenche
 * aussi la mission de ménage « après départ » (P2-MEN-01, CdC § 6.4).
 */
final class CheckOut
{
    public function __construct(
        private readonly CycleDuSejour $cycle,
        private readonly Factures $factures,
        private readonly Missions $missions,
    ) {}

    public function effectuer(Sejour $sejour, User $agent, int $cautionRetenue, ?string $motif): Sejour
    {
        if ($sejour->etat !== EtatDuSejour::Arrive) {
            throw new ErreurMetier('Seul un séjour arrivé peut faire l’objet d’un check-out.', 'checkout_impossible', 422);
        }
        if ($cautionRetenue < 0 || $cautionRetenue > $sejour->caution) {
            throw new ErreurMetier('La retenue sur la caution ne peut pas dépasser la caution encaissée ('.number_format($sejour->caution, 0, ',', ' ').' F).', 'caution_invalide', 422);
        }
        if ($cautionRetenue > 0 && trim((string) $motif) === '') {
            throw new ErreurMetier('Une retenue sur la caution doit toujours être motivée.', 'motif_obligatoire', 422);
        }

        return DB::transaction(function () use ($sejour, $agent, $cautionRetenue, $motif): Sejour {
            $this->cycle->passer($sejour, EtatDuSejour::Parti, $agent, [
                'parti_le' => now(), 'checkout_par' => $agent->id,
                'caution_retenue' => $cautionRetenue,
                'caution_retenue_motif' => $cautionRetenue > 0 ? trim((string) $motif) : null,
            ]);

            $this->missions->creerApresDepart($sejour);

            // « Un séjour, une facture » (CdC § 9.4) : la facture finale, sur le devis déjà figé.
            $dejaEmise = Facture::query()->where('sejour_id', $sejour->id)->where('type', TypeDeFacture::Facture->value)->exists();
            if (! $dejaEmise) {
                $this->factures->genererPourUnSejour($sejour, TypeDeFacture::Facture, $agent);
            }

            return $sejour->refresh();
        });
    }

    /**
     * Consommations déjà connues du système, pour l'écran de check-out — une simple lecture,
     * aucun nouveau calcul (CdC § 6.3). `hebergement` est le net à payer FIGÉ du séjour
     * (hébergement, extras et transfert éventuel de la réservation, taxes comprises) ; `repas`
     * et `transferts` sont les commandes et transferts demandés PENDANT le séjour (CdC § 6.6 et
     * « Repas et boissons »), réglés chacun par leur propre circuit — jamais inclus dans le net
     * à payer du séjour, donc jamais soustraits de lui.
     *
     * @return array{hebergement: int, repas: int, transferts: int, total: int}
     */
    public function consommations(Sejour $sejour): array
    {
        $repas = (int) $sejour->commandesRepas()
            ->whereNotIn('etat', [EtatDeCommande::Demande->value, EtatDeCommande::Annulee->value, EtatDeCommande::Refusee->value])
            ->sum('montant_total');

        $transferts = (int) $sejour->transferts()
            ->whereNotIn('etat', [EtatDuTransfert::Demande->value, EtatDuTransfert::Annule->value])
            ->sum('montant');

        return [
            'hebergement' => $sejour->net_a_payer,
            'repas' => $repas,
            'transferts' => $transferts,
            'total' => $sejour->net_a_payer + $repas + $transferts,
        ];
    }
}
