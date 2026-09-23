import { envoyer, lire } from '../../../shared/api/client'
import type { PageDe, PieceJustificative, Proprietaire, SaisieProprietaire, TypeDePiece } from './types'

export const listerLesProprietaires = (filtres: Record<string, unknown> = {}): Promise<PageDe<Proprietaire>> =>
  lire<PageDe<Proprietaire>>('/backoffice/proprietaires', filtres)

export const afficherLeProprietaire = (id: number): Promise<Proprietaire> =>
  lire<Proprietaire>(`/backoffice/proprietaires/${id}`)

export const creerUnProprietaire = (saisie: SaisieProprietaire): Promise<Proprietaire> =>
  envoyer<Proprietaire>('/backoffice/proprietaires', saisie)

export const modifierLeProprietaire = (id: number, saisie: Partial<SaisieProprietaire>): Promise<Proprietaire> =>
  envoyer<Proprietaire>(`/backoffice/proprietaires/${id}`, saisie, 'put')

export const listerLesPieces = (proprietaireId: number): Promise<PieceJustificative[]> =>
  lire<PieceJustificative[]>(`/backoffice/proprietaires/${proprietaireId}/pieces`)

export const deposerUnePiece = (proprietaireId: number, type: TypeDePiece, fichier: File, expireLe?: string): Promise<PieceJustificative> => {
  const formulaire = new FormData()
  formulaire.append('type', type)
  formulaire.append('fichier', fichier)
  if (expireLe) formulaire.append('expire_le', expireLe)
  return envoyer<PieceJustificative>(`/backoffice/proprietaires/${proprietaireId}/pieces`, formulaire)
}

export const deciderDUnePiece = (
  proprietaireId: number,
  pieceId: number,
  decision: 'valider' | 'refuser',
  motif?: string,
): Promise<PieceJustificative> =>
  envoyer<PieceJustificative>(`/backoffice/proprietaires/${proprietaireId}/pieces/${pieceId}/decision`, { decision, motif }, 'put')

export const supprimerUnePiece = (proprietaireId: number, pieceId: number): Promise<void> =>
  envoyer<void>(`/backoffice/proprietaires/${proprietaireId}/pieces/${pieceId}`, undefined, 'delete')
