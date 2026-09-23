<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Catalogue\Models\Proprietaire;
use App\Domain\Partenaires\Services\RetenueALaSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Proprietaire */
class ProprietaireResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $retenue = app(RetenueALaSource::class)->pour($this->resource);
        $manquants = $this->elementsManquants();

        return [
            'id' => $this->id,
            'nom_affiche' => $this->nomAffiche(),
            'interne' => $this->interne,
            'compte' => [
                'id' => $this->utilisateur->id,
                'nom' => $this->utilisateur->nom,
                'prenoms' => $this->utilisateur->prenoms,
                'email' => $this->utilisateur->email,
                'telephone' => $this->utilisateur->telephone,
                'statut' => $this->utilisateur->statut->value,
            ],
            'nature' => $this->nature->value,
            'nature_libelle' => $this->nature->libelle(),
            'raison_sociale' => $this->raison_sociale,
            'regime_fiscal' => $this->regime_fiscal->value,
            'regime_fiscal_libelle' => $this->regime_fiscal->libelle(),
            'assujetti_tva' => $this->assujetti_tva,
            'ncc' => $this->ncc,
            'rccm' => $this->rccm,
            'adresse' => $this->adresse,
            'mandat' => [
                'mode_remuneration' => $this->mode_remuneration->value,
                'mode_remuneration_libelle' => $this->mode_remuneration->libelle(),
                'taux_commission' => $this->taux_commission === null ? null : (float) $this->taux_commission,
                'part_entreprise_cautions' => $this->part_entreprise_cautions === null ? null : (float) $this->part_entreprise_cautions,
                'signe_le' => $this->mandat_signe_le?->format('d/m/Y'),
                'expire_le' => $this->mandat_expire_le?->format('d/m/Y'),
                'bons_valides_automatiquement' => $this->bons_valides_automatiquement,
            ],
            'notes' => $this->notes,
            // Ce que la plateforme retiendrait AUJOURD'HUI sur un reversement (CdC § 8.7).
            'retenue_a_la_source' => $retenue,
            'dossier_complet' => $manquants === [],
            'elements_manquants' => $manquants,
            'nombre_residences' => $this->whenCounted('residences'),
            'pieces' => PieceJustificativeResource::collection($this->whenLoaded('pieces')),
        ];
    }
}
