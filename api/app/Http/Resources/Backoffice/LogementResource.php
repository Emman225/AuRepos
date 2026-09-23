<?php

namespace App\Http\Resources\Backoffice;

use App\Domain\Catalogue\Models\Logement;
use App\Domain\Parametres\Services\Parametres;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue BACK OFFICE d'un logement : elle contient le prix propriétaire.
 * Ne jamais la servir au site public ni à l'application client.
 *
 * @mixin Logement
 */
class LogementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $parametres = app(Parametres::class);

        return [
            'id' => $this->id,
            'residence_id' => $this->residence_id,
            'reference' => $this->reference,
            'nom' => $this->nom,
            'type' => ['id' => $this->type->id, 'code' => $this->type->code, 'nom' => $this->type->nom],
            'resume' => $this->resume(),
            'nombre_pieces' => $this->nombre_pieces,
            'nombre_chambres' => $this->nombre_chambres,
            'nombre_lits' => $this->getAttribute('nombre_lits'),
            'nombre_salles_de_bain' => $this->getAttribute('nombre_salles_de_bain'),
            'capacite_de_base' => $this->capacite_de_base,
            'capacite_maximale' => $this->capacite_maximale,
            'surface_m2' => $this->getAttribute('surface_m2'),
            'description' => $this->getAttribute('description'),
            'regles' => [
                'fumeur_autorise' => $this->getAttribute('fumeur_autorise'),
                'animaux_autorises' => $this->getAttribute('animaux_autorises'),
                'fetes_autorisees' => $this->getAttribute('fetes_autorisees'),
                'texte' => $this->getAttribute('regles_maison'),
            ],
            // Horaires propres au logement, sinon ceux des Paramètres.
            'heure_arrivee' => substr((string) ($this->getAttribute('heure_arrivee') ?? $parametres->valeur('sejours.heure_arrivee')), 0, 5),
            'heure_depart' => substr((string) ($this->getAttribute('heure_depart') ?? $parametres->valeur('sejours.heure_depart')), 0, 5),
            'caution' => $this->caution,
            'duree_minimale' => $this->duree_minimale ?? $parametres->valeur('sejours.duree_minimale'),
            'duree_maximale' => $this->duree_maximale,
            'politique_annulation' => $this->politique_annulation->value,
            'politique_annulation_libelle' => $this->politique_annulation->libelle(),
            'prix_proprietaire' => $this->prix_proprietaire,
            'prix_vente' => $this->prix_vente,
            'marge_par_nuitee' => $this->prix_vente !== null && $this->prix_proprietaire !== null
                ? $this->prix_vente - $this->prix_proprietaire
                : null,
            'etat_publication' => $this->etat_publication->value,
            'etat_publication_libelle' => $this->etat_publication->libelle(),
            'mise_en_avant' => $this->mise_en_avant,
            'equipements' => $this->whenLoaded('equipements', fn () => $this->equipements->map->only(['id', 'nom', 'icone'])),
        ];
    }
}
