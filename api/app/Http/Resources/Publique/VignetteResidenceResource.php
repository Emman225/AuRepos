<?php

namespace App\Http\Resources\Publique;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Catalogue\Models\Residence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vignette d'une résidence sur la page d'accueil (CdC § 5.1) : mises en avant et mieux notées.
 * Une résidence n'a pas ses propres photos : la couverture vient de son premier logement publié.
 *
 * @mixin Residence
 */
class VignetteResidenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $quartier = $this->quartier;
        /** @var Logement|null $logement */
        $logement = $this->logements->first();
        /** @var PhotoLogement|null $couverture */
        $couverture = $logement?->photos->firstWhere('couverture', true) ?? $logement?->photos->first();

        return [
            'nom' => $this->nom,
            'slug' => $this->slug,
            // Pas de fiche résidence publique à ce jour : on renvoie vers son logement le moins cher.
            'logement_reference' => $logement?->reference,
            'lieu' => ['commune' => $quartier->commune->nom, 'quartier' => $quartier->nom],
            'photo' => $couverture?->urlVignette(),
            'a_partir_de' => $this->logements->pluck('prix_vente')->filter()->min(),
            'nombre_logements' => $this->logements->count(),
            'note_moyenne' => $this->getAttribute('note_moyenne_calc') !== null ? round((float) $this->getAttribute('note_moyenne_calc'), 1) : null,
        ];
    }
}
