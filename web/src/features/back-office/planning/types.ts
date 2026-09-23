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

/**
 * Un blocage de dates (maintenance, usage du propriétaire, saison fermée) — endpoint RÉEL et déjà
 * en production ailleurs (fiche logement, pas encore consommé côté planning avant P2-BO-01).
 * api/app/Http/Controllers/Api/V1/Backoffice/CalendrierController.php::presenter().
 */
export interface BlocageDuPlanning {
  id: number
  debut: string
  fin: string
  motif: string
  motif_libelle: string
  commentaire: string | null
}
