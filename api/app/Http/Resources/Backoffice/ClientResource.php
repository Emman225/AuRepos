<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Comptes\Models\User;
use App\Domain\Sejours\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Client|null $fiche */
        $fiche = $this->client;

        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'prenoms' => $this->prenoms,
            'nom_complet' => $this->nomComplet(),
            'email' => $this->email,
            'telephone' => $this->telephone,
            'statut' => $this->statut->value,
            'statut_libelle' => $this->statut->libelle(),
            'nature' => $fiche?->nature,
            'nature_libelle' => $fiche ? (Client::NATURES[$fiche->nature] ?? $fiche->nature) : null,
            'raison_sociale' => $fiche?->raison_sociale,
            'ncc' => $fiche?->ncc,
            'rccm' => $fiche?->rccm,
            'tva_hebergement' => $fiche?->tva_hebergement,
            'tva_transfert' => $fiche?->tva_transfert,
            'tva_motif' => $fiche?->tva_motif,
            'tva_motif_le' => $fiche?->tva_motif_le?->format('d/m/Y H:i:s'),
            'statut_a_terme' => $fiche?->statut_a_terme->value,
            'statut_a_terme_libelle' => $fiche?->statut_a_terme->libelle(),
            'plafond_credit' => $fiche?->plafond_credit,
            'liste_noire' => $fiche !== null && $fiche->liste_noire,
            'liste_noire_motif' => $fiche?->liste_noire_motif,
            'liste_noire_le' => $fiche?->liste_noire_le?->format('d/m/Y H:i:s'),
            'cree_le' => $this->created_at?->format('d/m/Y H:i:s'),
        ];
    }
}
