<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Audit\Services\JournalAudit;
use App\Domain\Validation\Models\ChangementAValider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChangementAValider */
class ChangementAValiderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $utilisateur = $request->user();

        return [
            'id' => $this->id,
            'sujet' => $this->sujet ? JournalAudit::libelleDe($this->sujet) : null,
            'sujet_type' => $this->sujet_type,
            'sujet_id' => $this->sujet_id,
            'champ' => $this->champ,
            'valeur_actuelle' => $this->valeur_actuelle,
            'valeur_proposee' => $this->valeur_proposee,
            'motif' => $this->motif,
            'statut' => $this->statut,
            'propose_par' => $this->auteur->nomComplet(),
            'propose_le' => $this->created_at?->format('d/m/Y H:i:s'),
            'decide_par' => $this->decideur?->nomComplet(),
            'decide_le' => $this->decide_le?->format('d/m/Y H:i:s'),
            'motif_decision' => $this->motif_decision,
            // Pour l'écran : le bouton « Valider » ne s'offre pas à l'auteur de la proposition.
            'je_peux_valider' => $this->enAttente() && $utilisateur !== null && $utilisateur->getAuthIdentifier() !== $this->propose_par,
            'je_peux_annuler' => $this->enAttente() && $utilisateur !== null && $utilisateur->getAuthIdentifier() === $this->propose_par,
        ];
    }
}
