<?php

namespace App\Domain\Tarification\Calcul;

/**
 * Résultat du calcul : tous les montants ET tous les taux qui les ont produits.
 * C'est cet objet qui sera FIGÉ sur le séjour à la réservation : un changement de
 * paramètre ne touche jamais un séjour déjà enregistré (CdC § 5.4).
 *
 * Il ne contient ni prix propriétaire ni marge : il peut être montré au client.
 * Tous les montants sont en francs CFA, entiers.
 */
final readonly class DevisDeSejour
{
    /**
     * @param  list<array{date: string, saison: string|null, tarif: int, origine: string}>  $nuitees
     * @param  list<array{code: string, libelle: string, quantite: int, montant_unitaire: int, montant: int}>  $supplements
     * @param  list<array{libelle: string, montant: int}>  $reductions
     * @param  list<array{libelle: string, montant_ht: int}>  $extras
     * @param  array{tva: float, tva_transfert: float, tdt: float, taxe_sejour_montant: int, taxe_sejour_base: string}  $taux
     */
    public function __construct(
        public int $nombreDeNuits,
        public array $nuitees,
        public int $hebergementBrutHt,
        public array $supplements,
        public int $supplementsHt,
        public float $remisePourcentage,
        public int $remiseHt,
        public array $reductions,
        public int $reductionsHt,
        public bool $reductionsPlafonnees,
        public int $hebergementNetHt,
        public array $extras,
        public int $extrasHt,
        public int $tvaHebergementEtExtras,
        public int $transfertHt,
        public int $tvaTransfert,
        public int $totalTtc,
        public int $tdt,
        public int $occupantsTaxables,
        public int $taxeDeSejour,
        public int $netAPayer,
        public int $caution,
        public int $totalAvecCaution,
        public array $taux,
    ) {}

    public function totalHt(): int
    {
        return $this->hebergementNetHt + $this->extrasHt + $this->transfertHt;
    }

    public function totalTva(): int
    {
        return $this->tvaHebergementEtExtras + $this->tvaTransfert;
    }

    /** Case « autres taxes » de la facture normalisée : TDT + taxe de séjour (CdC § 9.4). */
    public function autresTaxes(): int
    {
        return $this->tdt + $this->taxeDeSejour;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'nombre_de_nuits' => $this->nombreDeNuits,
            'nuitees' => $this->nuitees,
            'hebergement_brut_ht' => $this->hebergementBrutHt,
            'supplements' => $this->supplements,
            'supplements_ht' => $this->supplementsHt,
            'remise_pourcentage' => $this->remisePourcentage,
            'remise_ht' => $this->remiseHt,
            'reductions' => $this->reductions,
            'reductions_ht' => $this->reductionsHt,
            'reductions_plafonnees' => $this->reductionsPlafonnees,
            'hebergement_net_ht' => $this->hebergementNetHt,
            'extras' => $this->extras,
            'extras_ht' => $this->extrasHt,
            'transfert_ht' => $this->transfertHt,
            'total_ht' => $this->totalHt(),
            'tva_hebergement_et_extras' => $this->tvaHebergementEtExtras,
            'tva_transfert' => $this->tvaTransfert,
            'total_tva' => $this->totalTva(),
            'total_ttc' => $this->totalTtc,
            'tdt' => $this->tdt,
            'occupants_taxables' => $this->occupantsTaxables,
            'taxe_de_sejour' => $this->taxeDeSejour,
            'autres_taxes' => $this->autresTaxes(),
            'net_a_payer' => $this->netAPayer,
            // La caution n'est pas un produit : à part, jamais dans la base fiscale (CdC § 5.4).
            'caution' => $this->caution,
            'total_avec_caution' => $this->totalAvecCaution,
            'taux' => $this->taux,
        ];
    }
}
