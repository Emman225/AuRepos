<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Comptes\Models\User;
use App\Domain\Fidelite\Services\PointsDeFidelite;
use App\Domain\Sejours\Calcul\ResultatDuDevis;
use App\Domain\Sejours\Models\Client;
use App\Domain\Tarification\Calcul\CalculDuSejour;
use App\Domain\Tarification\Calcul\DemandeDeCalcul;
use App\Domain\Tarification\Services\CodesPromo;
use App\Domain\Tarification\Services\PrixNegocies;
use Illuminate\Support\Carbon;

/**
 * Chiffrage complet d'un séjour : prix négocié du client, code promo, points de fidélité,
 * moteur unique (CdC § 5.1, § 5.4, § 7.3). Utilisé À L'IDENTIQUE par la réservation directe
 * et par le devis autonome — pour que « prix figés » veuille dire la même chose partout.
 */
final class CalculComplet
{
    public function __construct(
        private readonly CalculDuSejour $calcul,
        private readonly PrixNegocies $prixNegocies,
        private readonly CodesPromo $codesPromo,
        private readonly PointsDeFidelite $points,
    ) {}

    /**
     * @param  array{arrivee: string, depart: string, adultes: int, enfants?: int, arrivee_tardive?: bool, depart_tardif?: bool,
     *               points_utilises?: int, code_promo?: string|null}  $saisie
     */
    public function pour(User $utilisateur, Client $client, Logement $logement, array $saisie): ResultatDuDevis
    {
        // Le prix négocié du client PRIME sur toute la grille (CdC § 7.3) ; s'il existe, il vaut pour tout le calcul.
        $tarifNegocie = $this->prixNegocies->pour($utilisateur, $logement->type_logement_id);
        $codePromo = filled($saisie['code_promo'] ?? null) ? $this->codesPromo->verifier((string) $saisie['code_promo'], $logement->residence) : null;

        // Points ET code promo s'évaluent AVANT le calcul, sur la même base hors réductions :
        // le moteur les reçoit comme deux réductions, il reste seul maître du plancher.
        $sansReductions = $this->calcul->calculer(new DemandeDeCalcul(
            logement: $logement,
            arrivee: Carbon::parse($saisie['arrivee']),
            depart: Carbon::parse($saisie['depart']),
            adultes: (int) $saisie['adultes'],
            enfants: (int) ($saisie['enfants'] ?? 0),
            arriveeTardive: (bool) ($saisie['arrivee_tardive'] ?? false),
            departTardif: (bool) ($saisie['depart_tardif'] ?? false),
            tarifNegocieParNuit: $tarifNegocie,
        ));

        $points = $this->pointsDemandes($utilisateur, $saisie, $sansReductions->hebergementNetHt);
        $reductions = $points['valeur'] > 0 ? [['libelle' => $points['utilisables'].' point(s) de fidélité', 'montant' => $points['valeur']]] : [];
        if ($codePromo !== null) {
            $reductions[] = ['libelle' => 'Code promo « '.$codePromo->code.' »', 'montant' => $this->codesPromo->calculerLaReduction($codePromo, $sansReductions->hebergementNetHt)];
        }

        $devis = $this->calcul->calculer(new DemandeDeCalcul(
            logement: $logement,
            arrivee: Carbon::parse($saisie['arrivee']),
            depart: Carbon::parse($saisie['depart']),
            adultes: (int) $saisie['adultes'],
            enfants: (int) ($saisie['enfants'] ?? 0),
            arriveeTardive: (bool) ($saisie['arrivee_tardive'] ?? false),
            departTardif: (bool) ($saisie['depart_tardif'] ?? false),
            reductions: $reductions,
            pointsUtilises: $points['utilisables'],
            tvaHebergementApplicable: $client->tva_hebergement,
            tvaTransfertApplicable: $client->tva_transfert,
            tarifNegocieParNuit: $tarifNegocie,
        ));

        return new ResultatDuDevis($devis, $points, $codePromo, $tarifNegocie);
    }

    /**
     * Points demandés, ramenés à ce que le client peut réellement utiliser (solde, minimum à payer).
     *
     * @param  array<string, mixed>  $saisie
     * @return array{solde: int, utilisables: int, valeur: int, valeur_du_point: int, plafonne: bool}
     */
    private function pointsDemandes(User $utilisateur, array $saisie, int $hebergementNetHtSansReductions): array
    {
        $demandes = (int) ($saisie['points_utilises'] ?? 0);
        if ($demandes <= 0) {
            return ['solde' => 0, 'utilisables' => 0, 'valeur' => 0, 'valeur_du_point' => 0, 'plafonne' => false];
        }

        return $this->points->utilisablesSur($utilisateur, $hebergementNetHtSansReductions, $demandes);
    }
}
