export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

/** api/app/Http/Resources/Backoffice/ChauffeurResource.php. */
export interface Chauffeur {
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

/** POST/PUT /backoffice/chauffeurs (ChauffeurRequest). */
export interface SaisieChauffeur {
  nom: string
  prenoms?: string
  email: string
  telephone?: string
  actif?: boolean
}

/** api/app/Http/Resources/Transferts/VehiculeResource.php — partagé back office / espace chauffeur. */
export interface Vehicule {
  id: number
  chauffeur_id: number
  type_vehicule_id: number
  type_vehicule: string | null
  immatriculation: string
  actif: boolean
}

/** POST/PUT /backoffice/chauffeurs/{chauffeur}/vehicules. */
export interface SaisieVehicule {
  type_vehicule_id: number
  immatriculation: string
  actif?: boolean
}
