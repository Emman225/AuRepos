import { envoyer, lire } from '../../../shared/api/client'
import type { PageDe, SaisieAffectation, TransfertBackOffice } from './types'

export const listerLesTransferts = (filtres: Record<string, unknown> = {}): Promise<PageDe<TransfertBackOffice>> =>
  lire<PageDe<TransfertBackOffice>>('/backoffice/transferts', filtres)

export const affecterLeTransfert = (id: number, saisie: SaisieAffectation): Promise<TransfertBackOffice> =>
  envoyer<TransfertBackOffice>(`/backoffice/transferts/${id}/affecter`, saisie)

export const annulerLeTransfert = (id: number): Promise<TransfertBackOffice> =>
  envoyer<TransfertBackOffice>(`/backoffice/transferts/${id}/annuler`)
