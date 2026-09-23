/** GET /apporteur/tableau-de-bord. */
export interface CompteursApporteur {
  nombre_filleuls: number
  nombre_commissions: number
  solde_du: number
}

/** GET /apporteur/filleuls. */
export interface Filleul {
  id: number
  nom_complet: string
  inscrit_le: string | null
}

/** GET /apporteur/commissions. */
export interface CommissionApporteur {
  id: number
  sejour_reference: string | null
  montant: number
  cree_le: string | null
}
