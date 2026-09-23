<?php

namespace App\Domain\Tarification\Services;

use App\Domain\Comptes\Models\User;
use App\Domain\Tarification\Models\PrixNegocie;
use App\Support\Api\ErreurMetier;

/**
 * Prix négociés par client et par type de logement (CdC § 7.3) : « ils priment sur tout
 * le reste ». Une seule négociation active par couple client/type ; l'admin qui en saisit
 * une nouvelle remplace l'ancienne — l'historique reste dans le journal d'audit.
 */
final class PrixNegocies
{
    /** Le tarif nuitée qui prime pour CE client sur CE type, ou null s'il n'y en a pas. */
    public function pour(User $client, int $typeLogementId): ?int
    {
        return PrixNegocie::query()
            ->where('client_id', $client->id)->where('type_logement_id', $typeLogementId)->where('actif', true)
            ->value('tarif_par_nuit');
    }

    public function enregistrer(User $client, int $typeLogementId, int $tarifParNuit, ?string $notes, User $auteur): PrixNegocie
    {
        if ($tarifParNuit < 1) {
            throw new ErreurMetier('Le tarif négocié doit être positif.', 'tarif_invalide', 422);
        }

        return PrixNegocie::updateOrCreate(
            ['client_id' => $client->id, 'type_logement_id' => $typeLogementId],
            ['tarif_par_nuit' => $tarifParNuit, 'actif' => true, 'notes' => $notes, 'modifie_par' => $auteur->id],
        );
    }

    public function desactiver(PrixNegocie $prixNegocie): void
    {
        $prixNegocie->update(['actif' => false]);
    }
}
