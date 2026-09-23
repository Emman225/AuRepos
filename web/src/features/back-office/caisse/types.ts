export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type Sens = 'encaissement' | 'decaissement'

export type Guichet = 'sejours' | 'creances_a_terme' | 'en_ligne' | 'avances' | 'dettes_partenaires'

export type ModeDeReglement = 'especes' | 'mobile_money' | 'carte' | 'virement' | 'cheque' | 'avance' | 'canal_externe'

export type EtatDuReglement = 'en_attente' | 'a_payer' | 'preuve_jointe' | 'effectue' | 'rejete'

export interface Imputation {
  affaire: string
  montant: number
}

export interface EtapeDuCircuit {
  par: string
  le: string
}

export interface Circuit {
  saisie: EtapeDuCircuit
  validation: EtapeDuCircuit | null
  preuve: (EtapeDuCircuit & { fichier: string }) | null
  finalisation: string | null
  rejet: { motif: string; le: string } | null
}

export interface ActionsSurLeReglement {
  valider: boolean
  joindre_la_preuve: boolean
  finaliser: boolean
  rejeter: boolean
}

export interface Reglement {
  id: number
  reference: string
  sens: Sens
  guichet: Guichet
  guichet_libelle: string
  agence: string | null
  tiers: { id: number; nom: string; profil: string }
  montant: number
  mode: ModeDeReglement
  mode_libelle: string
  reference_du_mode: string | null
  notes: string
  etat: EtatDuReglement
  etat_libelle: string
  numero_recu: string | null
  recu_envoye_le: string | null
  surplus_en_avance: boolean
  imputations?: Imputation[]
  circuit: Circuit
  actions: ActionsSurLeReglement
}

export interface Affaire {
  id: number
  reference: string
  etat: string
  logement: string
  arrivee: string
  depart: string
  acompte_exige: number
  net_a_payer: number
  encaisse: number
  encaisse_hors_avance: number
  en_cours: number
  reste_du: number
  solde: boolean
  acompte_atteint: boolean
}

export interface AffairesDuClient {
  client: { id: number; nom: string }
  affaires: Affaire[]
}

export interface SaisieEncaissement {
  client_id: number
  sejours: number[]
  montant: number
  mode: ModeDeReglement
  reference_du_mode?: string
  notes: string
  guichet?: 'sejours' | 'creances_a_terme'
  surplus_en_avance?: boolean
}

export interface SaisieDecaissement {
  beneficiaire_id: number
  montant: number
  mode: ModeDeReglement
  reference_du_mode?: string
  notes: string
}

export interface SaisieAvance {
  client_id: number
  montant: number
  mode: ModeDeReglement
  reference_du_mode?: string
  notes: string
}

export interface DepotAvance {
  id: number
  date: string
  montant: number
  solde: number
  utilise: number
  numero_recu: string | null
}

export interface SituationDesAvances {
  client: { id: number; nom: string }
  disponible: number
  depots: DepotAvance[]
}
