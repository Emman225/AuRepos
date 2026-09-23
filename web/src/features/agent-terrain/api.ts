import { envoyer, lire } from '../../shared/api/client'
import type { ConsommationsDuSejour, EtatDesLieux, LigneEtatDesLieux, Mission, OccupantDeSejour, SejourAgent, TypeEtatDesLieux } from './types'

// ------------------------------------------------------------- Mes séjours du jour (P2-MOB-04)

/** GET /agent/sejours (SejoursController::index) : arrivées confirmées dues + départs arrivés dus, ma journée. */
export const mesSejoursDuJour = (): Promise<SejourAgent[]> => lire<SejourAgent[]>('/agent/sejours')

export const afficherUnSejour = (id: number): Promise<SejourAgent> => lire<SejourAgent>(`/agent/sejours/${id}`)

export const listerLesOccupants = (id: number): Promise<OccupantDeSejour[]> =>
  lire<OccupantDeSejour[]>(`/agent/sejours/${id}/occupants`)

export const televerserUnePieceOccupant = (id: number, occupantId: number, fichier: File): Promise<OccupantDeSejour> => {
  const formulaire = new FormData()
  formulaire.append('fichier', fichier)
  return envoyer<OccupantDeSejour>(`/agent/sejours/${id}/occupants/${occupantId}/piece`, formulaire)
}

/** POST .../check-in (SejoursController::checkIn) : `code` = code d'arrivée saisi par l'agent, jamais affiché avant (CdC § 11). */
export const faireLeCheckIn = (id: number, code: string): Promise<SejourAgent> =>
  envoyer<SejourAgent>(`/agent/sejours/${id}/check-in`, { code })

export const afficherLesConsommations = (id: number): Promise<ConsommationsDuSejour> =>
  lire<ConsommationsDuSejour>(`/agent/sejours/${id}/consommations`)

/** POST .../check-out : `motif` reste facultatif côté API mais l'écran l'exige dès qu'une caution est retenue. */
export const faireLeCheckOut = (id: number, cautionRetenue: number, motif?: string): Promise<SejourAgent> =>
  envoyer<SejourAgent>(`/agent/sejours/${id}/check-out`, { caution_retenue: cautionRetenue, motif })

// ------------------------------------------------------------- État des lieux (P2-SEJ-02)

export const listerLesEtatsDesLieux = (id: number): Promise<EtatDesLieux[]> =>
  lire<EtatDesLieux[]>(`/agent/sejours/${id}/etats-des-lieux`)

export const etablirUnEtatDesLieux = (id: number, type: TypeEtatDesLieux, commentaireGeneral?: string): Promise<EtatDesLieux> =>
  envoyer<EtatDesLieux>(`/agent/sejours/${id}/etats-des-lieux`, { type, commentaire_general: commentaireGeneral })

/** Le PDF est un binaire brut (pas l'enveloppe JSON habituelle) : à ouvrir directement dans un nouvel onglet. */
export const urlPdfEtatDesLieux = (id: number): string =>
  `${(import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1').replace(/\/$/, '')}/agent/sejours/${id}/etats-des-lieux/pdf`

export const ajouterUneLigneEtatDesLieux = (id: number, etatDesLieuId: number, libelle: string, observation?: string): Promise<LigneEtatDesLieux> =>
  envoyer<LigneEtatDesLieux>(`/agent/sejours/${id}/etats-des-lieux/${etatDesLieuId}/lignes`, { libelle, observation })

export const ajouterUnePhotoDeLigne = (id: number, etatDesLieuId: number, ligneId: number, fichier: File): Promise<LigneEtatDesLieux> => {
  const formulaire = new FormData()
  formulaire.append('fichier', fichier)
  return envoyer<LigneEtatDesLieux>(`/agent/sejours/${id}/etats-des-lieux/${etatDesLieuId}/lignes/${ligneId}/photos`, formulaire)
}

/** `signature` : image signée à l'écran, encodée en base64 (EtatsDesLieuxController::signer — verrouille l'état des lieux). */
export const signerUnEtatDesLieux = (id: number, etatDesLieuId: number, signatureBase64: string): Promise<EtatDesLieux> =>
  envoyer<EtatDesLieux>(`/agent/sejours/${id}/etats-des-lieux/${etatDesLieuId}/signature`, { signature: signatureBase64 })

// ------------------------------------------------------------- Mes missions de ménage (P2-MEN-01)

/** GET /agent/missions (MissionsController::index) : seulement celles qui me sont affectées. */
export const mesMissions = (filtres: { statut?: string } = {}): Promise<Mission[]> => lire<Mission[]>('/agent/missions', filtres)

export const demarrerUneMission = (id: number): Promise<Mission> => envoyer<Mission>(`/agent/missions/${id}/debut`)

export const terminerUneMission = (id: number, notes?: string): Promise<Mission> =>
  envoyer<Mission>(`/agent/missions/${id}/fin`, { notes })
