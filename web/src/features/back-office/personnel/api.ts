import { envoyer, lire } from '../../../shared/api/client'
import type { Agence, PageDe, Personnel, ProfilPersonnel, SaisieAgence, SaisiePersonnel } from './types'

export const listerLePersonnel = (filtres: Record<string, unknown> = {}): Promise<PageDe<Personnel>> =>
  lire<PageDe<Personnel>>('/backoffice/personnel', filtres)

export const creerUnCompteDePersonnel = (saisie: SaisiePersonnel): Promise<Personnel> =>
  envoyer<Personnel>('/backoffice/personnel', saisie)

export const rattacherAuxResidences = (id: number, residences: number[]): Promise<Personnel> =>
  envoyer<Personnel>(`/backoffice/personnel/${id}/residences`, { residences }, 'put')

export const listerLesAgences = (filtres: Record<string, unknown> = {}): Promise<PageDe<Agence>> =>
  lire<PageDe<Agence>>('/backoffice/agences', filtres)

export const creerUneAgence = (saisie: SaisieAgence): Promise<Agence> => envoyer<Agence>('/backoffice/agences', saisie)

export const modifierUneAgence = (id: number, saisie: Partial<SaisieAgence>): Promise<Agence> =>
  envoyer<Agence>(`/backoffice/agences/${id}`, saisie, 'put')

export const PROFILS_PERSONNEL: ProfilPersonnel[] = ['super_administrateur', 'administrateur', 'gestionnaire', 'gouvernante', 'agent_assistance']
