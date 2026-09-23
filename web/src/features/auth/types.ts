/** Les espaces du site, tels que l'API les nomme (api/app/Domain/Comptes/Enums/Profil.php). */
export type Espace =
  | 'backoffice'
  | 'assistance'
  | 'proprietaire'
  | 'agent'
  | 'chauffeur'
  | 'livreur'
  | 'restaurateur'
  | 'apporteur'
  | 'client'

export type Profil =
  | 'super_administrateur'
  | 'administrateur'
  | 'gestionnaire'
  | 'gouvernante'
  | 'agent_assistance'
  | 'proprietaire'
  | 'agent_terrain'
  | 'chauffeur'
  | 'livreur'
  | 'restaurateur'
  | 'apporteur'
  | 'client'

export interface Utilisateur {
  id: number
  nom: string
  prenoms: string | null
  nom_complet: string
  email: string
  telephone: string | null
  profil: Profil
  profil_libelle: string
  espace: Espace
  agence: { id: number; nom: string } | null
  peut_encaisser: boolean
}

export interface SessionOuverte {
  jeton: string
  type: 'Bearer'
  expire_dans: number
  utilisateur: Utilisateur
}
