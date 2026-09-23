<?php

namespace App\Domain\Referentiels\Services;

use App\Domain\Comptes\Models\Agence;
use App\Domain\Referentiels\Models\Commune;
use App\Domain\Referentiels\Models\Equipement;
use App\Domain\Referentiels\Models\Quartier;
use App\Domain\Referentiels\Models\Region;
use App\Domain\Referentiels\Models\StatutMetier;
use App\Domain\Referentiels\Models\TypeLogement;
use App\Domain\Referentiels\Models\TypeVehicule;
use App\Domain\Referentiels\Models\Ville;
use App\Domain\Tarification\Models\Saison;
use App\Domain\Tarification\Models\Supplement;
use App\Domain\Tarification\Models\TrancheDuree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Décrit chaque référentiel une fois pour toutes. Le contrôleur et le service
 * sont génériques : ajouter un référentiel = ajouter une entrée ici.
 *
 * @phpstan-type Definition array{
 *   modele: class-string<Model>, libelle: string, public: bool, tri: list<string>,
 *   champs: list<string>, recherche: list<string>, actif: string, reserve_administrateurs?: bool,
 *   parent?: array{champ: string, relation: string, slug: string}
 * }
 */
final class RegistreDesReferentiels
{
    /** @return array<string, Definition> */
    public function tous(): array
    {
        return [
            'regions' => [
                'modele' => Region::class, 'libelle' => 'Régions', 'public' => true, 'actif' => 'actif',
                'tri' => ['nom'], 'champs' => ['nom', 'actif'], 'recherche' => ['nom'],
            ],
            'villes' => [
                'modele' => Ville::class, 'libelle' => 'Villes', 'public' => true, 'actif' => 'actif',
                'tri' => ['nom'], 'champs' => ['region_id', 'nom', 'actif'], 'recherche' => ['nom'],
                'parent' => ['champ' => 'region_id', 'relation' => 'region', 'slug' => 'regions'],
            ],
            'communes' => [
                'modele' => Commune::class, 'libelle' => 'Communes', 'public' => true, 'actif' => 'actif',
                'tri' => ['nom'], 'champs' => ['ville_id', 'nom', 'actif'], 'recherche' => ['nom'],
                'parent' => ['champ' => 'ville_id', 'relation' => 'ville', 'slug' => 'villes'],
            ],
            'quartiers' => [
                'modele' => Quartier::class, 'libelle' => 'Quartiers', 'public' => true, 'actif' => 'actif',
                'tri' => ['nom'], 'champs' => ['commune_id', 'nom', 'actif'], 'recherche' => ['nom'],
                'parent' => ['champ' => 'commune_id', 'relation' => 'commune', 'slug' => 'communes'],
            ],
            'types-logement' => [
                'modele' => TypeLogement::class, 'libelle' => 'Types de logement', 'public' => true, 'actif' => 'actif',
                'tri' => ['ordre', 'nom'], 'champs' => ['code', 'nom', 'nombre_pieces', 'ordre', 'actif'], 'recherche' => ['nom', 'code'],
            ],
            'equipements' => [
                'modele' => Equipement::class, 'libelle' => 'Équipements', 'public' => true, 'actif' => 'actif',
                'tri' => ['ordre', 'nom'], 'champs' => ['nom', 'portee', 'icone', 'filtre_recherche', 'ordre', 'actif'], 'recherche' => ['nom'],
            ],
            // Transferts (CdC § 6.6) : le client choisit son type de véhicule à la demande.
            'types-vehicule' => [
                'modele' => TypeVehicule::class, 'libelle' => 'Types de véhicules', 'public' => true, 'actif' => 'actif',
                'tri' => ['nom'], 'champs' => ['nom', 'capacite', 'actif'], 'recherche' => ['nom'],
            ],
            'statuts-metier' => [
                'modele' => StatutMetier::class, 'libelle' => 'Statuts métier', 'public' => false, 'reserve_administrateurs' => true, 'actif' => 'actif',
                'tri' => ['domaine', 'ordre'], 'champs' => ['domaine', 'code', 'libelle', 'couleur', 'ordre', 'actif'], 'recherche' => ['libelle', 'code', 'domaine'],
            ],
            // Tarification : réservée aux administrateurs (CdC § 7.3).
            'saisons' => [
                'modele' => Saison::class, 'libelle' => 'Saisons', 'public' => false, 'reserve_administrateurs' => true, 'actif' => 'actif',
                'tri' => ['date_debut'], 'champs' => ['nom', 'categorie', 'date_debut', 'date_fin', 'actif'], 'recherche' => ['nom'],
            ],
            'tranches-duree' => [
                'modele' => TrancheDuree::class, 'libelle' => 'Tranches de durée', 'public' => false, 'reserve_administrateurs' => true, 'actif' => 'actif',
                'tri' => ['nuits_min'], 'champs' => ['nom', 'nuits_min', 'nuits_max', 'actif'], 'recherche' => ['nom'],
            ],
            'supplements' => [
                'modele' => Supplement::class, 'libelle' => 'Suppléments', 'public' => false, 'reserve_administrateurs' => true, 'actif' => 'actif',
                'tri' => ['code'], 'champs' => ['code', 'nom', 'mode', 'montant', 'type_logement_id', 'actif'], 'recherche' => ['nom', 'code'],
            ],
            // Guichets d'encaissement : jamais publics.
            'agences' => [
                'modele' => Agence::class, 'libelle' => 'Agences', 'public' => false, 'reserve_administrateurs' => true, 'actif' => 'active',
                'tri' => ['nom'], 'champs' => ['nom', 'adresse', 'telephone', 'active'], 'recherche' => ['nom', 'adresse'],
            ],
        ];
    }

    /** @return Definition */
    public function definition(string $slug): array
    {
        return $this->tous()[$slug] ?? throw new NotFoundHttpException;
    }

    /**
     * Règles de validation ; `$id` est l'élément en cours de modification (pour l'unicité).
     *
     * @return array<string, list<mixed>>
     */
    public function regles(string $slug, ?int $id = null, mixed $parentId = null): array
    {
        $unique = fn (string $table, string $colonne = 'nom') => Rule::unique($table, $colonne)->ignore($id);

        return match ($slug) {
            'regions' => ['nom' => ['required', 'string', 'max:100', $unique('regions')], 'actif' => ['boolean']],
            'villes' => [
                'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
                'nom' => ['required', 'string', 'max:100', $unique('villes')->where('region_id', $parentId)],
                'actif' => ['boolean'],
            ],
            'communes' => [
                'ville_id' => ['required', 'integer', Rule::exists('villes', 'id')],
                'nom' => ['required', 'string', 'max:100', $unique('communes')->where('ville_id', $parentId)],
                'actif' => ['boolean'],
            ],
            'quartiers' => [
                'commune_id' => ['required', 'integer', Rule::exists('communes', 'id')],
                'nom' => ['required', 'string', 'max:100', $unique('quartiers')->where('commune_id', $parentId)],
                'actif' => ['boolean'],
            ],
            'types-logement' => [
                'code' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9_]+$/', $unique('types_logement', 'code')],
                'nom' => ['required', 'string', 'max:100', $unique('types_logement')],
                'nombre_pieces' => ['nullable', 'integer', 'min:1', 'max:30'],
                'ordre' => ['integer', 'min:0', 'max:9999'],
                'actif' => ['boolean'],
            ],
            'equipements' => [
                'nom' => ['required', 'string', 'max:100', $unique('equipements')],
                'portee' => ['required', Rule::in(['logement', 'residence'])],
                'icone' => ['nullable', 'string', 'max:60'],
                'filtre_recherche' => ['boolean'],
                'ordre' => ['integer', 'min:0', 'max:9999'],
                'actif' => ['boolean'],
            ],
            'types-vehicule' => [
                'nom' => ['required', 'string', 'max:100', $unique('types_vehicule')],
                'capacite' => ['nullable', 'integer', 'min:1', 'max:100'],
                'actif' => ['boolean'],
            ],
            'statuts-metier' => [
                'domaine' => ['required', 'string', 'max:40', 'regex:/^[a-z_]+$/'],
                'code' => ['required', 'string', 'max:40', 'regex:/^[a-z_]+$/', $unique('statuts_metier', 'code')->where('domaine', $parentId)],
                'libelle' => ['required', 'string', 'max:100'],
                'couleur' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'ordre' => ['integer', 'min:0', 'max:9999'],
                'actif' => ['boolean'],
            ],
            'saisons' => [
                'nom' => ['required', 'string', 'max:100'],
                'categorie' => ['required', Rule::in(['basse', 'haute', 'evenement'])],
                'date_debut' => ['required', 'date'],
                'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
                'actif' => ['boolean'],
            ],
            'tranches-duree' => [
                'nom' => ['required', 'string', 'max:100'],
                'nuits_min' => ['required', 'integer', 'min:1', 'max:3650'],
                'nuits_max' => ['nullable', 'integer', 'max:3650', 'gte:nuits_min'],
                'actif' => ['boolean'],
            ],
            'supplements' => [
                'code' => ['required', Rule::in(['occupant_supplementaire', 'week_end', 'arrivee_tardive', 'depart_tardif'])],
                'nom' => ['required', 'string', 'max:100'],
                'mode' => ['required', Rule::in(['par_nuit_et_par_personne', 'par_nuit', 'forfait'])],
                'montant' => ['required', 'integer', 'min:0', 'max:100000000'],
                'type_logement_id' => ['nullable', 'integer', Rule::exists('types_logement', 'id')],
                'actif' => ['boolean'],
            ],
            'agences' => [
                'nom' => ['required', 'string', 'max:255', $unique('agences')],
                'adresse' => ['nullable', 'string', 'max:255'],
                'telephone' => ['nullable', 'string', 'max:30'],
                'active' => ['boolean'],
            ],
            default => throw new NotFoundHttpException,
        };
    }

    /** @return array<string, string> */
    public function libelles(): array
    {
        return [
            'nom' => 'nom', 'code' => 'code', 'libelle' => 'libellé', 'domaine' => 'domaine', 'couleur' => 'couleur',
            'region_id' => 'région', 'ville_id' => 'ville', 'commune_id' => 'commune', 'portee' => 'portée',
            'categorie' => 'catégorie', 'date_debut' => 'date de début', 'date_fin' => 'date de fin', 'nuits_min' => 'nombre minimal de nuits',
            'nuits_max' => 'nombre maximal de nuits', 'mode' => 'mode de calcul', 'montant' => 'montant', 'type_logement_id' => 'type de logement',
            'nombre_pieces' => 'nombre de pièces', 'ordre' => 'ordre', 'adresse' => 'adresse', 'telephone' => 'téléphone',
            'capacite' => 'capacité',
        ];
    }
}
