export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type StatutDeMission = 'a_faire' | 'en_cours' | 'faite'

/** api/app/Http/Resources/Exploitation/MissionResource.php. */
export interface Mission {
  id: number
  type: string
  type_libelle: string
  origine: string
  origine_libelle: string
  statut: StatutDeMission
  statut_libelle: string
  logement: { id: number; nom: string; residence: string | null } | null
  sejour: { reference: string; arrivee: string; depart: string } | null
  /** Nom complet de l'agent affecté ; jamais son identifiant (MissionResource ne l'expose pas). */
  agent: string | null
  echeance: string
  notes: string | null
  debutee_le: string | null
  terminee_le: string | null
  created_at: string | null
}

/** POST /backoffice/logements/{logement}/missions : ménage demandé, ad hoc, motivé (CdC § 6.4). */
export interface SaisieDemandeMenage {
  motif: string
  echeance: string
}
