import { api, envoyer, lire } from '../../../shared/api/client'
import type { Facture, PageDe, StatutDeTransmissionFne, TypeDeFacture } from './types'

export const listerLesFactures = (filtres: Record<string, unknown> = {}): Promise<PageDe<Facture>> =>
  lire<PageDe<Facture>>('/backoffice/factures', filtres)

export const afficherLaFacture = (id: number): Promise<Facture> => lire<Facture>(`/backoffice/factures/${id}`)

export const genererUneFacture = (sejourId: number, type: Extract<TypeDeFacture, 'proforma' | 'facture'>): Promise<Facture> =>
  envoyer<Facture>('/backoffice/factures', { sejour_id: sejourId, type })

export const transmettreLaFacture = (id: number): Promise<Facture> =>
  envoyer<Facture>(`/backoffice/factures/${id}/transmission`, undefined, 'put')

export const emettreUnAvoir = (id: number, motif: string): Promise<Facture> =>
  envoyer<Facture>(`/backoffice/factures/${id}/avoir`, { motif })

/** Le jeton part comme pour tout appel (intercepteur) : une simple navigation `<a href>` ne le pourrait pas. */
export async function ouvrirLaFacturePdf(id: number): Promise<void> {
  const reponse = await api.get<Blob>(`/backoffice/factures/${id}/pdf`, { responseType: 'blob' })
  const urlObjet = URL.createObjectURL(reponse.data)
  window.open(urlObjet, '_blank')
  setTimeout(() => URL.revokeObjectURL(urlObjet), 60_000)
}

export const STATUTS_TRANSMISSION: StatutDeTransmissionFne[] = ['a_transmettre', 'transmise', 'refusee', 'non_configuree']
export const TYPES_DE_FACTURE: TypeDeFacture[] = ['proforma', 'facture', 'avoir']
