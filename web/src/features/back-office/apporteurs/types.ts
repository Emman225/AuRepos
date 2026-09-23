export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

/** api/app/Http/Resources/Backoffice/ApporteurResource.php. */
export interface Apporteur {
  id: number
  code: string
  pourcentage: number
  actif: boolean
  compte: {
    id: number
    nom: string
    prenoms: string | null
    nom_complet: string
    email: string
    telephone: string | null
    statut: string
  }
}

/** api/app/Http/Resources/Backoffice/CommissionApporteurResource.php. */
export interface CommissionApporteur {
  id: number
  sejour_id: number
  sejour_reference: string | null
  reglement_id: number
  montant: number
  cree_le: string | null
}

/** GET /backoffice/apporteurs/{id}/commissions. */
export interface CommissionsDeLApporteur {
  solde_du: number
  commissions: CommissionApporteur[]
}

/** POST/PUT /backoffice/apporteurs. */
export interface SaisieApporteur {
  nom: string
  prenoms?: string
  email: string
  telephone?: string
  pourcentage: number
  actif?: boolean
}
