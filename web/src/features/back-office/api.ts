import { lire } from '../../shared/api/client'
import type { CompteursDuJour } from './types'

export const compteursDuJour = (): Promise<CompteursDuJour> => lire<CompteursDuJour>('/backoffice/tableau-de-bord')
