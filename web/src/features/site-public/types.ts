/** Vignette d'un logement publié (api/app/Http/Resources/Publique/VignetteLogementResource.php). */
export interface VignetteLogement {
  reference: string
  nom: string
  residence: string
  resume: string
  lieu: { commune: string; quartier: string }
  capacite_maximale: number
  prix_par_nuit: number | null
  photo: string | null
  note_moyenne: number | null
}

export interface Banniere {
  id: number
  titre: string
  sous_titre: string | null
  image: string
  lien: string | null
}

export interface Diapositive {
  id: number
  image: string
  legende: string | null
  lien: string | null
}

export interface Temoignage {
  id: number
  nom_client: string
  message: string
  note: number | null
  photo: string | null
}

/** Vignette d'une résidence (api/app/Http/Resources/Publique/VignetteResidenceResource.php). */
export interface VignetteResidence {
  nom: string
  slug: string
  logement_reference: string | null
  lieu: { commune: string; quartier: string }
  photo: string | null
  a_partir_de: number | null
  nombre_logements: number
  note_moyenne: number | null
}

/** GET /accueil (api/app/Http/Controllers/Api/V1/AccueilController.php). */
export interface DonneesAccueil {
  carrousel: Diapositive[]
  mises_en_avant: VignetteLogement[]
  residences_mises_en_avant: VignetteResidence[]
  residences_mieux_notees: VignetteResidence[]
  bannieres: Banniere[]
  temoignages: Temoignage[]
}

/** Type de logement, tel que rendu par GET /referentiels/types-logement. */
export interface TypeLogementChoix {
  id: number
  code: string
  nom: string
  nombre_pieces: number | null
}

/** GET /referentiels/communes. */
export interface CommuneChoix {
  id: number
  nom: string
}

/** GET /referentiels/quartiers?commune_id=… */
export interface QuartierChoix {
  id: number
  nom: string
  commune_id: number
}

/** GET /referentiels/types-vehicule (CdC § 6.6, transferts). */
export interface TypeVehiculeChoix {
  id: number
  nom: string
}

/** GET /referentiels/equipements — seuls ceux marqués filtre_recherche intéressent la recherche publique. */
export interface EquipementChoix {
  id: number
  nom: string
  portee: 'logement' | 'residence'
  filtre_recherche: boolean
}

export interface Pagination {
  page: number
  par_page: number
  total: number
  derniere_page: number
}

/** GET /catalogue/recherche (api/app/Http/Controllers/Api/V1/CatalogueController.php). */
export interface ResultatsRecherche {
  avec_dates: boolean
  arrivee: string
  depart: string
  elements: VignetteLogement[]
  pagination: Pagination
}

/** GET /blog (api/app/Http/Resources/Publique/ArticleResumeResource.php). */
export interface ArticleResume {
  titre: string
  slug: string
  resume: string | null
  image: string | null
  publie_le: string | null
}

export interface ResultatsBlog {
  elements: ArticleResume[]
  pagination: Pagination
}

/** GET /blog/{slug} (api/app/Http/Resources/Publique/ArticleResource.php). */
export interface Article {
  titre: string
  slug: string
  resume: string | null
  contenu: string
  image: string | null
  publie_le: string | null
}

interface PhotoLogement {
  url: string
  url_vignette: string
  legende: string | null
  couverture: boolean
}

interface TarifDeSaison {
  saison: string
  categorie: string
  debut: string
  fin: string
  tarif: number
}

interface Avis {
  note: number
  commentaire: string | null
  client: string
  depose_le: string | null
}

/** GET /catalogue/logements/{reference} (api/app/Http/Resources/Publique/LogementPublicResource.php). */
export interface LogementFiche {
  reference: string
  nom: string
  resume: string
  type: { code: string; nom: string }
  residence: { nom: string; slug: string; description: string | null }
  lieu: { commune: string; quartier: string }
  nombre_pieces: number
  nombre_chambres: number
  nombre_lits: number | null
  nombre_salles_de_bain: number | null
  capacite_de_base: number
  capacite_maximale: number
  surface_m2: number | null
  description: string | null
  regles: { fumeur_autorise: boolean; animaux_autorises: boolean; fetes_autorisees: boolean; texte: string | null }
  heure_arrivee: string
  heure_depart: string
  prix_par_nuit: number
  devise: string
  caution: number
  duree_minimale: number | null
  duree_maximale: number | null
  politique_annulation: string
  politique_annulation_libelle: string
  equipements: { nom: string; icone: string | null; portee: string }[]
  photos: PhotoLogement[]
  tarifs_par_saison: TarifDeSaison[]
  note_moyenne: number | null
  avis: Avis[]
  similaires: VignetteLogement[]
}

/** GET /catalogue/logements/{reference}/disponibilite?mois=AAAA-MM */
export interface DisponibiliteDuMois {
  mois: string
  jours_occupes: string[]
}

export interface CriteresRecherche {
  arrivee?: string
  depart?: string
  adultes?: number
  enfants?: number
  commune_id?: number
  quartier_id?: number
  type_logement_id?: number
  budget_max?: number
  equipements?: number[]
  page?: number
}
