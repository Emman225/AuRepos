export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

/** api/app/Http/Resources/Repas/LivreurResource.php. */
export interface Livreur {
  id: number
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

/** POST/PUT /backoffice/livreurs. */
export interface SaisieLivreur {
  nom: string
  prenoms?: string
  email: string
  telephone?: string
  actif?: boolean
}

/** api/app/Domain/Repas/Services/GestionDesCommandes.php::gainsDuLivreur(). */
export interface GainsLivreur {
  total_gagne: number
  deja_verse: number
  solde_du: number
}
