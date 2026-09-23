export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export interface Tranche {
  id: number
  nom: string
  nuits_min: number
  nuits_max: number | null
}

export interface LigneDeSaison {
  id: number
  nom: string
  categorie: 'basse' | 'haute' | 'evenement'
  date_debut: string
  date_fin: string
  /** Clé = id de la tranche (nombre, sérialisé en chaîne par JSON). */
  tarifs: Record<number, number | null>
}

/** GET .../tarification/grille (GrilleTarifaireController::afficher). */
export interface Grille {
  cible: { type_logement_id: number } | { logement_id: number }
  tranches: Tranche[]
  saisons: LigneDeSaison[]
}

export interface LigneSaisie {
  saison_id: number
  tranche_duree_id: number
  tarif: number | null
}

export interface Anomalie {
  niveau: 'bloquant' | 'information'
  code: string
  message: string
}

export interface RapportDeVerification {
  muette: boolean
  nombre_bloquantes: number
  anomalies: Anomalie[]
}

export interface NuiteeSimulee {
  date: string
  saison: string | null
  tarif: number
  origine: string
}

/** GET .../tarification/simulation. */
export interface Simulation {
  logement: string
  nombre_de_nuits: number
  tranche: string | null
  nuitees: NuiteeSimulee[]
  hebergement_hors_taxes: number
  marge_hebergement: number | null
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

export interface Derogation {
  logement_id: number
  reference: string
  nom: string
  residence: string
  taux_global: number
  taux_derogation: number | null
}

export interface PrixNegocie {
  id: number
  client: { id: number; nom: string; email: string }
  type_logement: { id: number; nom: string }
  tarif_par_nuit: number
  actif: boolean
  notes: string | null
  modifie_le: string | null
}

export interface SaisiePrixNegocie {
  client_id: number
  type_logement_id: number
  tarif_par_nuit: number
  notes?: string
}

export type TypeDeCodePromo = 'pourcentage' | 'montant'

export interface CodePromo {
  id: number
  code: string
  type: TypeDeCodePromo
  valeur: number
  date_debut: string
  date_fin: string
  residence: { id: number; nom: string } | null
  actif: boolean
  description: string | null
  valable_aujourd_hui: boolean
}

export interface SaisieCodePromo {
  code: string
  type: TypeDeCodePromo
  valeur: number
  date_debut: string
  date_fin: string
  residence_id?: number
  description?: string
}

export interface LigneReferentiel {
  id: number
  actif: boolean
  [champ: string]: unknown
}

/** api/app/Http/Resources/Repas/BaremeLivraisonRepasResource.php. */
export interface BaremeLivraisonRepas {
  id: number
  residence_id: number
  residence: string | null
  forfait: number
}

export interface SaisieBaremeLivraisonRepas {
  residence_id: number
  forfait: number
}

/** api/app/Http/Resources/Backoffice/BaremeTransfertResource.php. */
export interface BaremeTransfert {
  id: number
  commune_id: number
  commune: string | null
  type_vehicule_id: number
  type_vehicule: string | null
  prix: number
}

export interface SaisieBaremeTransfert {
  commune_id: number
  type_vehicule_id: number
  prix: number
}
