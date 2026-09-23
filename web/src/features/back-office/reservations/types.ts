import type { DevisDeSejour } from '../../reservation/types'

export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

interface LogementDeLaReservation {
  id: number
  reference: string
  nom: string
  residence: string
  residence_id: number
}

/** api/app/Domain/Caisse/Services/SoldeDesSejours.php::de(). */
export interface SoldeDuSejour {
  net_a_payer: number
  encaisse: number
  encaisse_hors_avance: number
  en_cours: number
  reste_du: number
  solde: boolean
  acompte_atteint: boolean
}

/** Vue back office d'un séjour (api/app/Http/Resources/Backoffice/SejourResource.php) : jamais le code d'arrivée. */
export interface ReservationBackOffice {
  id: number
  reference: string
  etat: string
  etat_libelle: string
  canal: string
  client: { id: number; nom: string; email: string; telephone: string | null } | null
  logement: LogementDeLaReservation
  arrivee: string
  depart: string
  nombre_de_nuits: number
  adultes: number
  enfants: number
  mode_reglement: string
  bon_de_commande: string | null
  net_a_payer: number
  caution: number
  acompte_exige: number
  reglement: SoldeDuSejour
  expire_le: string | null
  confirme_le: string | null
  agent_accueil_id: number | null
  obstacles_a_la_confirmation: string[]
  code_d_arrivee_emis: boolean
  /** Absent sur les séjours antérieurs à l'introduction du devis figé (colonne nullable côté API). */
  devis: DevisDeSejour | null
  arrive_le: string | null
  parti_le: string | null
  no_show_le: string | null
  caution_retenue: number | null
  caution_retenue_motif: string | null
}

/** api/app/Http/Resources/Backoffice/SejoursController::occupants() (Sejours\OccupantResource). */
export interface OccupantDeSejour {
  id: number
  nom: string
  prenoms: string | null
  enfant: boolean
  type_piece: string | null
  /** Jamais le numéro de pièce brut côté back office (CdC § 11) : seulement « fournie ou non ». */
  piece_fournie: boolean
  telephone: string | null
  pieces: { id: number; statut: string; nom_original: string }[]
}

/** api/app/Domain/Sejours/Services/CheckOut.php::consommations() — un seul objet récapitulatif, pas une liste de lignes. */
export interface ConsommationsDuSejour {
  hebergement: number
  repas: number
  transferts: number
  total: number
}

export type TypeEtatDesLieux = 'entree' | 'sortie'

/** api/app/Http/Resources/Sejours/LigneEtatDesLieuxResource.php — jamais d'URL de photo, seulement son nom d'origine. */
export interface LigneEtatDesLieux {
  id: number
  libelle: string
  observation: string | null
  ordre: number
  photos: { id: number; nom_original: string }[]
}

/** api/app/Http/Resources/Sejours/EtatDesLieuxResource.php. */
export interface EtatDesLieux {
  id: number
  type: TypeEtatDesLieux
  type_libelle: string
  commentaire_general: string | null
  signe: boolean
  signe_le: string | null
  etabli_par: string | null
  lignes: LigneEtatDesLieux[]
}

/** api/app/Http/Resources/Backoffice/DevisResource.php. */
export interface DevisBackOffice {
  id: number
  reference: string
  etat: 'en_attente' | 'transforme' | 'archive'
  client: { id: number; nom: string; email: string; telephone: string | null }
  logement: LogementDeLaReservation
  arrivee: string
  depart: string
  adultes: number
  enfants: number
  net_a_payer: number
  sejour: string | null
  cree_le: string | null
}

export type Canal = 'telephone' | 'walk_in' | 'canal_externe'
export type ModeReglementBackOffice = 'en_ligne' | 'agence' | 'a_terme'

/** POST /backoffice/sejours (ReservationManuelleRequest) : champs essentiels, le reste (points, code promo, occupants…) suit un usage courant. */
export interface SaisieReservationManuelle {
  canal: Canal
  client_id?: number
  client?: { nom: string; prenoms?: string; email: string; telephone?: string }
  reference_logement: string
  arrivee: string
  depart: string
  adultes: number
  enfants?: number
  mode_reglement: ModeReglementBackOffice
  bon_de_commande?: string
}

/** api/app/Http/Resources/Backoffice/ChangementAValiderResource.php. */
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
