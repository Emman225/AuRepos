<?php

namespace App\Domain\Catalogue\Services;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Parametres\Services\Parametres;
use Illuminate\Support\Collection;

/**
 * Contrôle des tarifs propriétaires par rapport à la médiane du type (CdC § 7.3) :
 * « chaque tarif propriétaire est comparé à la médiane de son type ; les écarts
 * sont signalés avant tout recalcul. »
 *
 * Un SIGNAL, jamais un blocage : « tarif hors médiane » est l'un des motifs de refus
 * qu'un administrateur peut choisir à la publication (CdC § 7.1), au même titre qu'une
 * photo floue — c'est un jugement humain, pas une règle automatique.
 */
final class ControleDesTarifs
{
    public function __construct(private readonly Parametres $parametres) {}

    /**
     * Médiane des prix propriétaires ARRÊTÉS des logements du même type (tous résidences
     * confondues, CdC § 7.3 ne restreint pas à une résidence). Le logement lui-même en est
     * exclu : sinon un prix déjà hors norme tirerait sa propre référence vers lui.
     */
    public function medianeDuType(int $typeLogementId, ?int $exclureLogementId = null): ?int
    {
        $prix = Logement::query()
            ->where('type_logement_id', $typeLogementId)
            ->whereNotNull('prix_proprietaire')
            ->when($exclureLogementId, fn ($q, int $id) => $q->where('id', '<>', $id))
            ->orderBy('prix_proprietaire')
            ->pluck('prix_proprietaire');

        return $this->mediane($prix);
    }

    /**
     * L'écart de ce logement à la médiane de son type, ou null s'il n'y a rien à comparer
     * (prix pas encore arrêté, ou aucun autre logement du type n'en a un).
     *
     * @return array{mediane_du_type: int, prix_proprietaire: int, ecart_pourcent: float, hors_mediane: bool}|null
     */
    public function ecart(Logement $logement): ?array
    {
        if ($logement->prix_proprietaire === null) {
            return null;
        }

        $mediane = $this->medianeDuType($logement->type_logement_id, $logement->id);
        if ($mediane === null || $mediane === 0) {
            return null;
        }

        $ecartPourcent = round((($logement->prix_proprietaire - $mediane) / $mediane) * 100, 1);
        $seuil = (float) $this->parametres->valeur('proprietaires.ecart_mediane_seuil');

        return [
            'mediane_du_type' => $mediane,
            'prix_proprietaire' => $logement->prix_proprietaire,
            'ecart_pourcent' => $ecartPourcent,
            'hors_mediane' => abs($ecartPourcent) > $seuil,
        ];
    }

    /** @param  Collection<int, int>  $valeurs  triées croissant */
    private function mediane(Collection $valeurs): ?int
    {
        $n = $valeurs->count();
        if ($n === 0) {
            return null;
        }

        $valeurs = $valeurs->values();
        $milieu = intdiv($n, 2);

        return $n % 2 === 1
            ? (int) $valeurs[$milieu]
            : (int) round(((int) $valeurs[$milieu - 1] + (int) $valeurs[$milieu]) / 2);
    }
}
