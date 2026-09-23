<?php

namespace App\Http\Resources\Publique;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vignette d'un résultat de recherche (CdC § 5.1) : photo de couverture + fiche courte.
 * Liste blanche stricte, comme la fiche : ni prix propriétaire, ni adresse, ni propriétaire.
 *
 * @mixin Logement
 */
class VignetteLogementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $quartier = $this->residence->quartier;
        /** @var PhotoLogement|null $couverture */
        $couverture = $this->photos->firstWhere('couverture', true) ?? $this->photos->first();

        return [
            'reference' => $this->reference,
            'nom' => $this->nom,
            'residence' => $this->residence->nom,
            'resume' => $this->resume(),
            'lieu' => ['commune' => $quartier->commune->nom, 'quartier' => $quartier->nom],
            'capacite_maximale' => $this->capacite_maximale,
            // Prix moyen par nuit SUR LES DATES DEMANDÉES, hors taxes, calculé par le serveur.
            'prix_par_nuit' => $this->getAttribute('prix_par_nuit_calcule'),
            'photo' => $couverture?->urlVignette(),
            'note_moyenne' => $this->avisPublies->isNotEmpty() ? round($this->avisPublies->avg('note'), 1) : null,
        ];
    }
}
