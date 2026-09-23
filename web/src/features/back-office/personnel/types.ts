export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type ProfilPersonnel = 'super_administrateur' | 'administrateur' | 'gestionnaire' | 'gouvernante' | 'agent_assistance'
export type StatutCompte = 'en_attente' | 'actif' | 'bloque'

/** api/app/Http/Resources/Backoffice/PersonnelResource.php. */
export interface Personnel {
  id: number
  nom: string
  prenoms: string | null
  nom_complet: string
  email: string
  telephone: string | null
  identifiant: string
  profil: ProfilPersonnel
  profil_libelle: string
  statut: StatutCompte
  agence: { id: number; nom: string } | null
  residences?: { id: number; nom: string }[]
  cree_le: string | null
}

export interface SaisiePersonnel {
  nom: string
  prenoms?: string
  email: string
  telephone?: string
  profil: ProfilPersonnel
  agence_id?: number
  residences?: number[]
}

/** api/app/Http/Resources/Backoffice/AgenceResource.php. */
export interface Agence {
  id: number
  nom: string
  adresse: string | null
  telephone: string | null
  active: boolean
  nombre_utilisateurs: number | null
  cree_le: string | null
}

export interface SaisieAgence {
  nom: string
  adresse?: string
  telephone?: string
  active?: boolean
}
