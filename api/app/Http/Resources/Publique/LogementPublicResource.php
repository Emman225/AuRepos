<?php

namespace App\Http\Resources\Publique;

use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use App\Domain\Catalogue\Models\PhotoLogement;
use App\Domain\Parametres\Services\Parametres;
use App\Domain\Sejours\Models\Avis;
use App\Domain\Tarification\Services\Tarifs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ce que le PUBLIC voit d'un logement (site et application client).
 *
 * Liste blanche stricte. N'y figurent JAMAIS : le prix propriétaire, la marge,
 * l'adresse exacte, les consignes d'accès, l'identité du propriétaire. Le seul prix
 * affiché est le prix de vente fixé par l'administrateur (CdC § 5.1 et § 7.2).
 *
 * @mixin Logement
 */
class LogementPublicResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $parametres = app(Parametres::class);
        $residence = $this->residence;
        $quartier = $residence->quartier;

        return [
            'reference' => $this->reference,
            'nom' => $this->nom,
            'resume' => $this->resume(),
            'type' => ['code' => $this->type->code, 'nom' => $this->type->nom],
            'residence' => ['nom' => $residence->nom, 'slug' => $residence->slug, 'description' => $residence->description],
            // Le quartier seulement ; l'adresse exacte est remise après confirmation du séjour.
            'lieu' => ['commune' => $quartier->commune->nom, 'quartier' => $quartier->nom],
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
            'heure_arrivee' => substr((string) ($this->getAttribute('heure_arrivee') ?? $parametres->valeur('sejours.heure_arrivee')), 0, 5),
            'heure_depart' => substr((string) ($this->getAttribute('heure_depart') ?? $parametres->valeur('sejours.heure_depart')), 0, 5),
            'prix_par_nuit' => $this->prix_vente,
            'devise' => $parametres->valeur('general.devise'),
            'caution' => $this->caution,
            'duree_minimale' => $this->duree_minimale ?? $parametres->valeur('sejours.duree_minimale'),
            'duree_maximale' => $this->duree_maximale,
            'politique_annulation' => $this->politique_annulation->value,
            'politique_annulation_libelle' => $this->politique_annulation->libelle(),
            'equipements' => $this->equipements->merge($residence->equipements)->unique('id')->values()->map->only(['nom', 'icone', 'portee']),
            'photos' => $this->photos->where('etat', 'acceptee')->sortBy('ordre')->values()->map(fn (PhotoLogement $p): array => [
                'url' => $p->urlAffichage(), 'url_vignette' => $p->urlVignette(), 'legende' => $p->legende, 'couverture' => $p->couverture,
            ]),
            // Aperçu « à partir de » par saison, sur la tranche la plus courte — jamais un calcul de séjour (CdC § 5.1).
            'tarifs_par_saison' => app(Tarifs::class)->parSaisonPour($this->resource),
            'note_moyenne' => $this->avisPublies->isNotEmpty() ? round($this->avisPublies->avg('note'), 1) : null,
            // Avis vérifiés (P2-AVI-01) : seuls les avis PUBLIÉS, jamais ceux en attente ou refusés.
            'avis' => $this->avisPublies->map(fn (Avis $a): array => [
                'note' => $a->note,
                'commentaire' => $a->commentaire,
                // Prénom + initiale seulement : jamais le nom complet d'un client sur une page publique.
                'client' => $a->sejour->client ? ($a->sejour->client->prenoms ?? $a->sejour->client->nom).' '.mb_substr($a->sejour->client->nom, 0, 1).'.' : '',
                'depose_le' => $a->created_at?->format('d/m/Y'),
            ]),
            'similaires' => VignetteLogementResource::collection(
                Logement::query()
                    ->where('id', '<>', $this->id)
                    ->where('type_logement_id', $this->type_logement_id)
                    ->where('etat_publication', EtatPublication::Publie)
                    ->whereRelation('residence', 'active', true)
                    ->with(['type', 'photos', 'residence.quartier.commune', 'avisPublies'])
                    ->inRandomOrder()
                    ->limit(4)
                    ->get()
                    ->each(fn (Logement $l) => $l->setAttribute('prix_par_nuit_calcule', $l->prix_vente)),
            ),
        ];
    }
}
