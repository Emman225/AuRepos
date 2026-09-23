import { envoyer, lire } from '../../../shared/api/client'
import type { Client, ClientATerme, PageDe, PieceJustificative, TypeDePiece } from './types'

export const listerLesClients = (filtres: Record<string, unknown> = {}): Promise<PageDe<Client>> =>
  lire<PageDe<Client>>('/backoffice/clients', filtres)

export const afficherLeClient = (id: number): Promise<Client> => lire<Client>(`/backoffice/clients/${id}`)

export const basculerLaTva = (id: number, saisie: { tva_hebergement?: boolean; tva_transfert?: boolean; motif: string }): Promise<Client> =>
  envoyer<Client>(`/backoffice/clients/${id}/tva`, saisie, 'put')

export const basculerLaListeNoire = (id: number, enListeNoire: boolean, motif?: string): Promise<Client> =>
  envoyer<Client>(`/backoffice/clients/${id}/liste-noire`, { en_liste_noire: enListeNoire, motif }, 'put')

export const modifierLaFicheDuClient = (
  id: number,
  saisie: { raison_sociale?: string; ncc?: string; rccm?: string },
): Promise<Client> => envoyer<Client>(`/backoffice/clients/${id}/fiche`, saisie, 'put')

export const listerLesComptesATerme = (filtres: Record<string, unknown> = {}): Promise<PageDe<ClientATerme>> =>
  lire<PageDe<ClientATerme>>('/backoffice/clients-a-terme', filtres)

export const afficherLeCompteATerme = (id: number): Promise<ClientATerme> => lire<ClientATerme>(`/backoffice/clients-a-terme/${id}`)

export const deciderDuCompteATerme = (
  id: number,
  decision: 'accepter' | 'refuser',
  plafondCredit?: number,
  motif?: string,
): Promise<ClientATerme> =>
  envoyer<ClientATerme>(`/backoffice/clients-a-terme/${id}/decision`, { decision, plafond_credit: plafondCredit, motif }, 'put')

export const listerLesPiecesATerme = (id: number): Promise<PieceJustificative[]> =>
  lire<PieceJustificative[]>(`/backoffice/clients-a-terme/${id}/pieces`)

export const deposerUnePieceATerme = (id: number, type: TypeDePiece, fichier: File): Promise<PieceJustificative> => {
  const formulaire = new FormData()
  formulaire.append('type', type)
  formulaire.append('fichier', fichier)
  return envoyer<PieceJustificative>(`/backoffice/clients-a-terme/${id}/pieces`, formulaire)
}

export const deciderDUnePieceATerme = (
  id: number,
  pieceId: number,
  decision: 'valider' | 'refuser',
  motif?: string,
): Promise<PieceJustificative> =>
  envoyer<PieceJustificative>(`/backoffice/clients-a-terme/${id}/pieces/${pieceId}/decision`, { decision, motif }, 'put')

export const supprimerUnePieceATerme = (id: number, pieceId: number): Promise<void> =>
  envoyer<void>(`/backoffice/clients-a-terme/${id}/pieces/${pieceId}`, undefined, 'delete')
