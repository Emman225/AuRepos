export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type ModeDeVente = 'logement' | 'type'
export type Disponibilite = 'disponible' | 'occupee'

export interface EquipementChoix {
  id: number
  nom: string
  icone: string | null
}

/** api/app/Http/Resources/Backoffice/ResidenceResource.php. */
export interface Residence {
  id: number
  nom: string
  slug: string
  proprietaire: { id: number; nom: string; interne: boolean }
  lieu: { quartier_id: number; quartier: string; commune_id: number; commune: string; libelle: string }
  adresse: string | null
  repere: string | null
  latitude: number | null
  longitude: number | null
  description: string | null
  consignes_acces: string | null
  mode_vente: ModeDeVente
  disponibilite: Disponibilite
  reouverture_prevue_le: string | null
  active: boolean
  nombre_logements?: number
  equipements?: EquipementChoix[]
  logements?: Logement[]
}

/** POST/PUT .../residences (ResidenceRequest). */
export interface SaisieResidence {
  proprietaire_id: number
  quartier_id: number
  nom: string
  adresse?: string
  repere?: string
  latitude?: number
  longitude?: number
  description?: string
  consignes_acces?: string
  mode_vente?: ModeDeVente
  active?: boolean
  equipements?: number[]
}

export type EtatPublication = 'brouillon' | 'en_attente' | 'refuse' | 'prix_a_negocier' | 'publie' | 'suspendu'
export type PolitiqueAnnulation = 'flexible' | 'moderee' | 'stricte'

/** api/app/Http/Resources/Backoffice/LogementResource.php. */
export interface Logement {
  id: number
  residence_id: number
  reference: string
  nom: string
  type: { id: number; code: string; nom: string }
  resume: string
  nombre_pieces: number
  nombre_chambres: number | null
  nombre_lits: number | null
  nombre_salles_de_bain: number | null
  capacite_de_base: number
  capacite_maximale: number
  surface_m2: number | null
  description: string | null
  regles: { fumeur_autorise: boolean; animaux_autorises: boolean; fetes_autorisees: boolean; texte: string | null }
  heure_arrivee: string | null
  heure_depart: string | null
  caution: number
  duree_minimale: number | null
  duree_maximale: number | null
  politique_annulation: PolitiqueAnnulation
  politique_annulation_libelle: string
  prix_proprietaire: number | null
  prix_vente: number | null
  marge_par_nuitee: number | null
  etat_publication: EtatPublication
  etat_publication_libelle: string
  mise_en_avant: boolean
  equipements?: EquipementChoix[]
}

/** POST/PUT .../logements (LogementRequest). */
export interface SaisieLogement {
  type_logement_id: number
  nom: string
  nombre_pieces: number
  nombre_chambres?: number
  nombre_lits?: number
  nombre_salles_de_bain?: number
  capacite_de_base: number
  capacite_maximale: number
  surface_m2?: number
  description?: string
  fumeur_autorise?: boolean
  animaux_autorises?: boolean
  fetes_autorisees?: boolean
  regles_maison?: string
  heure_arrivee?: string
  heure_depart?: string
  caution?: number
  duree_minimale?: number
  duree_maximale?: number
  politique_annulation?: PolitiqueAnnulation
  mise_en_avant?: boolean
  equipements?: number[]
}

export interface ChangementAValider {
  id: number
  sujet: string | null
  sujet_type: string | null
  sujet_id: number | null
  champ: string
  valeur_actuelle: string | null
  valeur_proposee: string | null
  motif: string | null
  statut: 'en_attente' | 'valide' | 'refuse' | 'annule'
  propose_par: string
  propose_le: string | null
  decide_par: string | null
  decide_le: string | null
  motif_decision: string | null
  je_peux_valider: boolean
  je_peux_annuler: boolean
}

interface Negociation {
  id: number
  date: string
  partie: string
  auteur: string
  montant: number
  nature: string
  commentaire: string | null
}

/** GET .../prix (PrixLogementController::afficher / situation()). */
export interface SituationPrix {
  prix_proprietaire: number | null
  prix_vente: number | null
  marge_par_nuitee: number | null
  pourcentage_entreprise: number
  pourcentage_entreprise_derogation: number | null
  prix_de_vente_conseille: number | null
  marge_conseillee_respectee: boolean | null
  controle_mediane: unknown
  changement_en_attente: ChangementAValider | null
  derogation_en_attente: ChangementAValider | null
  negociation: Negociation[]
}

/** GET .../publication (PublicationLogementController::afficher). */
export interface SituationPublication {
  etat: EtatPublication
  etat_libelle: string
  motif: string | null
  obstacles: string[]
  controle_mediane: unknown
}

export type ActionDePublication = 'soumettre' | 'publier' | 'refuser' | 'suspendre' | 'reactiver'

/** api/app/Http/Resources/Backoffice/PhotoLogementResource.php. */
export interface PhotoLogement {
  id: number
  legende: string | null
  ordre: number
  couverture: boolean
  url: string
  url_vignette: string
  largeur: number
  hauteur: number
  ajoutee_par_administration: boolean
  etat: 'en_attente' | 'acceptee' | 'refusee'
}

/** Réponse commune à toutes les actions sur la galerie. */
export interface Galerie {
  photos: PhotoLogement[]
  nombre: number
  minimum: number
  maximum: number
  assez_pour_publier: boolean
}

/** Ligne générique d'un référentiel (api/app/Http/Controllers/Api/V1/ReferentielsController.php). */
export interface LigneReferentiel {
  id: number
  actif: boolean
  parent?: string
  [champ: string]: unknown
}
