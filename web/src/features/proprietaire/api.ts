import { lire } from '../../shared/api/client'
import type { CompteursProprietaire, LogementProprietaire, ResidenceProprietaire, SejourProprietaire } from './types'

export const compteursDuProprietaire = (): Promise<CompteursProprietaire> => lire<CompteursProprietaire>('/proprietaire/tableau-de-bord')

export const mesResidences = (): Promise<ResidenceProprietaire[]> => lire<ResidenceProprietaire[]>('/proprietaire/residences')

export const logementsDeLaResidence = (residenceId: number): Promise<LogementProprietaire[]> =>
  lire<LogementProprietaire[]>(`/proprietaire/residences/${residenceId}/logements`)

export const sejoursDuLogement = (logementId: number): Promise<SejourProprietaire[]> =>
  lire<SejourProprietaire[]>(`/proprietaire/logements/${logementId}/sejours`)
