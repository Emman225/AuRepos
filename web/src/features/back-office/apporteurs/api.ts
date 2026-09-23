import { envoyer, lire } from '../../../shared/api/client'
import type { Apporteur, CommissionsDeLApporteur, PageDe, SaisieApporteur } from './types'

export const listerLesApporteurs = (filtres: Record<string, unknown> = {}): Promise<PageDe<Apporteur>> =>
  lire<PageDe<Apporteur>>('/backoffice/apporteurs', filtres)

export const creerUnApporteur = (saisie: SaisieApporteur): Promise<Apporteur> => envoyer<Apporteur>('/backoffice/apporteurs', saisie)

export const modifierLApporteur = (id: number, saisie: Partial<SaisieApporteur>): Promise<Apporteur> =>
  envoyer<Apporteur>(`/backoffice/apporteurs/${id}`, saisie, 'put')

export const commissionsDeLApporteur = (id: number): Promise<CommissionsDeLApporteur> =>
  lire<CommissionsDeLApporteur>(`/backoffice/apporteurs/${id}/commissions`)
