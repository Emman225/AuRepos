import { lire } from '../../../shared/api/client'
import type { DonneesPlanning } from './types'

export interface FiltresPlanning {
  du: string
  au: string
  residence_id?: number
}

export const chargerLePlanning = (filtres: FiltresPlanning): Promise<DonneesPlanning> =>
  lire<DonneesPlanning>('/backoffice/planning', { ...filtres })
