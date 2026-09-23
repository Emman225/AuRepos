export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type EtatDeLaReclamation = 'ouverte' | 'en_cours' | 'fermee'

/** api/app/Http/Resources/Assistance/ReclamationResource.php. */
export interface Reclamation {
  id: number
  sejour: { reference: string; logement: string } | null
  client: string
  motif: string
  statut: EtatDeLaReclamation
  statut_libelle: string
  reponse: string | null
  /** Reste `null` tant que le trésorier n'a pas confirmé la proposition (file des changements). */
  avoir_montant: number | null
  avoir_motif: string | null
  fermee_le: string | null
  created_at: string | null
}

/** api/app/Http/Resources/Backoffice/ChangementAValiderResource.php — copie locale (convention du dépôt, pas de type partagé entre écrans). */
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
