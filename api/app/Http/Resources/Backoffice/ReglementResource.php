<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Caisse\Enums\EtatDuReglement;
use App\Domain\Caisse\Models\Imputation;
use App\Domain\Caisse\Models\Reglement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reglement */
class ReglementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $moi = $request->user()?->getAuthIdentifier();
        $administrateur = $request->user()?->profil->estAdministrateur() ?? false;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sens' => $this->sens,
            'guichet' => $this->guichet->value,
            'guichet_libelle' => $this->guichet->libelle(),
            'agence' => $this->agence->nom,
            'tiers' => ['id' => $this->tiers->id, 'nom' => $this->tiers->nomComplet(), 'profil' => $this->tiers->profil->libelle()],
            'montant' => $this->montant,
            'mode' => $this->mode->value,
            'mode_libelle' => $this->mode->libelle(),
            'reference_du_mode' => $this->reference_du_mode,
            'notes' => $this->notes,
            'etat' => $this->etat->value,
            'etat_libelle' => $this->etat->libelle(),
            'numero_recu' => $this->numero_recu,
            'recu_envoye_le' => $this->recu_envoye_le?->format('d/m/Y H:i:s'),
            'surplus_en_avance' => $this->surplus_en_avance,
            'imputations' => $this->whenLoaded('imputations', fn () => $this->imputations->map(fn (Imputation $i): array => [
                'affaire' => $i->affaire?->reference ?? $i->affaire_type.' n° '.$i->affaire_id,
                'montant' => $i->montant,
            ])),
            // Qui a fait quoi, et quand : la traçabilité du circuit (CdC § 8.2).
            'circuit' => [
                'saisie' => ['par' => $this->auteur->nomComplet(), 'le' => $this->saisi_le->format('d/m/Y H:i:s')],
                'validation' => $this->valide_par ? ['par' => $this->validateur?->nomComplet(), 'le' => $this->valide_le?->format('d/m/Y H:i:s')] : null,
                'preuve' => $this->preuve_par ? ['par' => $this->porteurDeLaPreuve?->nomComplet(), 'le' => $this->preuve_le?->format('d/m/Y H:i:s'), 'fichier' => $this->preuve_nom] : null,
                'finalisation' => $this->finalise_le?->format('d/m/Y H:i:s'),
                'rejet' => $this->etat === EtatDuReglement::Rejete ? ['motif' => $this->motif_rejet, 'le' => $this->getAttribute('rejete_le')?->format('d/m/Y H:i:s')] : null,
            ],
            // Ce que CE compte peut faire maintenant : l'écran n'offre que les boutons utiles. Le serveur, lui, revérifie tout.
            'actions' => [
                'valider' => $administrateur && $this->etat === EtatDuReglement::EnAttente && $moi !== $this->saisi_par,
                'joindre_la_preuve' => $administrateur && $this->etat === EtatDuReglement::APayer && ! in_array($moi, [$this->saisi_par, $this->valide_par], true),
                'finaliser' => $administrateur && $this->etat === EtatDuReglement::PreuveJointe && $moi === $this->preuve_par,
                'rejeter' => $administrateur && $this->etat->enCours(),
            ],
        ];
    }
}
