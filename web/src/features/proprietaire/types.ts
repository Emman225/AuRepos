/** GET /proprietaire/tableau-de-bord (api/app/Http/Controllers/Api/V1/Proprietaire/TableauDeBordController.php). */
export interface CompteursProprietaire {
  nombre_residences: number
  nombre_logements: number
  sejours_en_cours: number
  arrivees_sous_7_jours: number
}

/** api/app/Http/Resources/Proprietaire/ResidenceResource.php. */
export interface ResidenceProprietaire {
  id: number
  nom: string
  lieu: { commune: string; quartier: string; libelle: string }
  nombre_logements: number
  disponibilite: string
  disponibilite_libelle: string
  active: boolean
}

/** api/app/Http/Resources/Proprietaire/LogementResource.php. */
export interface LogementProprietaire {
  id: number
  reference: string
  nom: string
  resume: string
  capacite_de_base: number
  capacite_maximale: number
  etat_publication: string
  etat_publication_libelle: string
}

/** api/app/Http/Resources/Proprietaire/SejourResource.php. */
export interface SejourProprietaire {
  reference: string
  etat: string
  etat_libelle: string
  arrivee: string
  depart: string
  nombre_de_nuits: number
  client: string
}
