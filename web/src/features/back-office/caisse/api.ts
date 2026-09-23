import { api, envoyer, lire } from '../../../shared/api/client'
import type { AffairesDuClient, PageDe, Reglement, SaisieAvance, SaisieDecaissement, SaisieEncaissement, SituationDesAvances } from './types'

export const listerLesReglements = (filtres: Record<string, unknown> = {}): Promise<PageDe<Reglement>> =>
  lire<PageDe<Reglement>>('/backoffice/caisse/reglements', filtres)

export const afficherLesAffairesDuClient = (clientId: number): Promise<AffairesDuClient> =>
  lire<AffairesDuClient>(`/backoffice/caisse/clients/${clientId}/affaires`)

export const encaisser = (saisie: SaisieEncaissement): Promise<Reglement> =>
  envoyer<Reglement>('/backoffice/caisse/encaissements', saisie)

export const decaisser = (saisie: SaisieDecaissement): Promise<Reglement> =>
  envoyer<Reglement>('/backoffice/caisse/decaissements', saisie)

export const validerUnReglement = (id: number): Promise<Reglement> =>
  envoyer<Reglement>(`/backoffice/caisse/reglements/${id}/validation`, undefined, 'put')

export const joindreLaPreuve = (id: number, fichier: File): Promise<Reglement> => {
  const formulaire = new FormData()
  formulaire.append('justificatif', fichier)
  return envoyer<Reglement>(`/backoffice/caisse/reglements/${id}/preuve`, formulaire)
}

export const finaliserUnReglement = (id: number): Promise<Reglement> =>
  envoyer<Reglement>(`/backoffice/caisse/reglements/${id}/finalisation`, undefined, 'put')

export const rejeterUnReglement = (id: number, motif: string): Promise<Reglement> =>
  envoyer<Reglement>(`/backoffice/caisse/reglements/${id}/rejet`, { motif }, 'put')

/** Le jeton part comme pour tout appel (intercepteur) : une simple navigation `<a href>` ne le pourrait pas. */
async function ouvrirUnFichier(url: string): Promise<void> {
  const reponse = await api.get<Blob>(url, { responseType: 'blob' })
  const urlObjet = URL.createObjectURL(reponse.data)
  window.open(urlObjet, '_blank')
  setTimeout(() => URL.revokeObjectURL(urlObjet), 60_000)
}

export const ouvrirLaPreuve = (id: number): Promise<void> => ouvrirUnFichier(`/backoffice/caisse/reglements/${id}/preuve`)

export const ouvrirLeRecu = (id: number): Promise<void> => ouvrirUnFichier(`/backoffice/caisse/reglements/${id}/recu`)

export const renvoyerLeRecu = (id: number): Promise<{ envoye: boolean }> =>
  envoyer<{ envoye: boolean }>(`/backoffice/caisse/reglements/${id}/recu/renvoi`)

export const afficherLaSituationDesAvances = (clientId: number): Promise<SituationDesAvances> =>
  lire<SituationDesAvances>(`/backoffice/caisse/clients/${clientId}/avances`)

export const deposerUneAvance = (saisie: SaisieAvance): Promise<Reglement> =>
  envoyer<Reglement>('/backoffice/caisse/avances', saisie)
