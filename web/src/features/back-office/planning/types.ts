export interface LogementDuPlanning {
  id: number
  reference: string
  nom: string
  residence: { id: number; nom: string }
}

/** api/app/Http/Resources/Backoffice/PlanningSejourResource.php. */
export interface SejourDuPlanning {
  id: number
  reference: string
  logement_id: number
  etat: string
  etat_libelle: string
  client_nom: string
  arrivee: string
  depart: string
}

/** GET /backoffice/planning?du=&au=&residence_id= (api/app/Http/Controllers/Api/V1/Backoffice/PlanningController.php). */
export interface DonneesPlanning {
  logements: LogementDuPlanning[]
  sejours: SejourDuPlanning[]
}
