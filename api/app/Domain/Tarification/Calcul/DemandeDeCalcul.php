<?php

namespace App\Domain\Tarification\Calcul;

use App\Domain\Catalogue\Models\Logement;
use Illuminate\Support\Carbon;

/**
 * Ce que le client (ou la réception) DEMANDE : des intentions, jamais des prix.
 * Tout montant présent ici est soit un tarif déjà arrêté par le serveur (extra du
 * catalogue, barème de transfert), soit une réduction décidée par le back office.
 */
final readonly class DemandeDeCalcul
{
    /**
     * @param  list<array{libelle: string, montant_ht: int}>  $extras  prestations et repas, hors taxes
     * @param  list<array{libelle: string, montant: int}>  $reductions  code promo, points de fidélité : montants HT
     */
    public function __construct(
        public Logement $logement,
        public Carbon $arrivee,
        public Carbon $depart,
        public int $adultes = 1,
        public int $enfants = 0,
        public bool $arriveeTardive = false,
        public bool $departTardif = false,
        /** Réduction en % du HT, saisie par un administrateur et confirmée par le trésorier (CdC § 6.1). */
        public float $remisePourcentage = 0.0,
        public array $reductions = [],
        /** Points de fidélité que le client veut utiliser ; leur valeur en francs entre dans $reductions. */
        public int $pointsUtilises = 0,
        public array $extras = [],
        public int $transfertHt = 0,
        /** Bascules TVA du client : exonération légale (TVAD) ou conventionnelle (TVAC), séparément (CdC § 5.4). */
        public bool $tvaHebergementApplicable = true,
        public bool $tvaTransfertApplicable = true,
        /** Prix négocié du client pour ce type de logement : il prime sur toute la grille (CdC § 7.3). */
        public ?int $tarifNegocieParNuit = null,
    ) {}

    public function occupants(): int
    {
        return $this->adultes + $this->enfants;
    }

    public function nombreDeNuits(): int
    {
        return (int) $this->arrivee->copy()->startOfDay()->diffInDays($this->depart->copy()->startOfDay());
    }
}
