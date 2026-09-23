import { envoyer, lire } from '../../../shared/api/client'
import type { GainsLivreur, Livreur, PageDe, SaisieLivreur } from './types'

export const listerLesLivreurs = (filtres: Record<string, unknown> = {}): Promise<PageDe<Livreur>> =>
  lire<PageDe<Livreur>>('/backoffice/livreurs', filtres)

export const creerUnLivreur = (saisie: SaisieLivreur): Promise<Livreur> => envoyer<Livreur>('/backoffice/livreurs', saisie)

export const modifierLeLivreur = (id: number, saisie: Partial<SaisieLivreur>): Promise<Livreur> =>
  envoyer<Livreur>(`/backoffice/livreurs/${id}`, saisie, 'put')

export const gainsDuLivreur = (id: number): Promise<GainsLivreur> => lire<GainsLivreur>(`/backoffice/livreurs/${id}/gains`)
