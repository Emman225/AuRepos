import { envoyer, lire } from '../../../shared/api/client'
import type {
  ChangementAValider,
  ConsommationsDuSejour,
  DevisBackOffice,
  EtatDesLieux,
  LigneEtatDesLieux,
  OccupantDeSejour,
  PageDe,
  ReservationBackOffice,
  SaisieReservationManuelle,
  TypeEtatDesLieux,
} from './types'

export interface FiltresReservations {
  etat?: string
  mode_reglement?: string
  champ_date?: 'arrivee' | 'depart'
  du?: string
  au?: string
  recherche?: string
  page?: number
  par_page?: number
}

export const listerLesReservations = (filtres: FiltresReservations = {}): Promise<PageDe<ReservationBackOffice>> =>
  lire<PageDe<ReservationBackOffice>>('/backoffice/sejours', filtres as Record<string, unknown>)

export const afficherLaReservation = (id: number): Promise<ReservationBackOffice> =>
  lire<ReservationBackOffice>(`/backoffice/sejours/${id}`)

export const reserverManuellement = (saisie: SaisieReservationManuelle): Promise<ReservationBackOffice> =>
  envoyer<ReservationBackOffice>('/backoffice/sejours', saisie)

export const confirmerLaReservation = (id: number): Promise<ReservationBackOffice> =>
  envoyer<ReservationBackOffice>(`/backoffice/sejours/${id}/confirmation`)

/** Réservé aux administrateurs (CdC § 6.1) : le trésorier désigné confirme ensuite, séparément. */
export const proposerUneReduction = (id: number, pourcentage: number, motif: string): Promise<ChangementAValider> =>
  envoyer<ChangementAValider>(`/backoffice/sejours/${id}/reduction`, { pourcentage, motif })

export const listerLesDevis = (filtres: { etat?: string; page?: number; par_page?: number } = {}): Promise<PageDe<DevisBackOffice>> =>
  lire<PageDe<DevisBackOffice>>('/backoffice/devis', filtres)

// ------------------------------------------------------------- Cycle de vie du séjour (P2-SEJ-*)

export const listerLesOccupants = (id: number): Promise<OccupantDeSejour[]> =>
  lire<OccupantDeSejour[]>(`/backoffice/sejours/${id}/occupants`)

export const televerserUnePieceOccupant = (id: number, occupantId: number, fichier: File): Promise<OccupantDeSejour> => {
  const formulaire = new FormData()
  formulaire.append('fichier', fichier)
  return envoyer<OccupantDeSejour>(`/backoffice/sejours/${id}/occupants/${occupantId}/piece`, formulaire)
}

/** POST .../check-in (SejoursController::checkIn) : `code` = code d'arrivée saisi par l'agent, jamais affiché avant (CdC § 11). */
export const faireLeCheckIn = (id: number, code: string): Promise<ReservationBackOffice> =>
  envoyer<ReservationBackOffice>(`/backoffice/sejours/${id}/check-in`, { code })

export const afficherLesConsommations = (id: number): Promise<ConsommationsDuSejour> =>
  lire<ConsommationsDuSejour>(`/backoffice/sejours/${id}/consommations`)

/** POST .../check-out : `motif` reste facultatif côté API mais l'écran l'exige dès qu'une caution est retenue. */
export const faireLeCheckOut = (id: number, cautionRetenue: number, motif?: string): Promise<ReservationBackOffice> =>
  envoyer<ReservationBackOffice>(`/backoffice/sejours/${id}/check-out`, { caution_retenue: cautionRetenue, motif })

/** PUT .../depart : prolongation ou départ anticipé, recalcul du devis côté API (CdC § 6.1). */
export const modifierLeDepart = (id: number, depart: string): Promise<ReservationBackOffice> =>
  envoyer<ReservationBackOffice>(`/backoffice/sejours/${id}/depart`, { depart }, 'put')

// ------------------------------------------------------------- État des lieux (P2-SEJ-02)

export const listerLesEtatsDesLieux = (id: number): Promise<EtatDesLieux[]> =>
  lire<EtatDesLieux[]>(`/backoffice/sejours/${id}/etats-des-lieux`)

export const etablirUnEtatDesLieux = (id: number, type: TypeEtatDesLieux, commentaireGeneral?: string): Promise<EtatDesLieux> =>
  envoyer<EtatDesLieux>(`/backoffice/sejours/${id}/etats-des-lieux`, { type, commentaire_general: commentaireGeneral })

/** Le PDF est un binaire brut (pas l'enveloppe JSON habituelle) : à ouvrir directement dans un nouvel onglet. */
export const urlPdfEtatDesLieux = (id: number): string =>
  `${(import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1').replace(/\/$/, '')}/backoffice/sejours/${id}/etats-des-lieux/pdf`

/** Rend la LIGNE créée (pas l'état des lieux entier) : EtatsDesLieuxController::ajouterUneLigne. */
export const ajouterUneLigneEtatDesLieux = (id: number, etatDesLieuId: number, libelle: string, observation?: string): Promise<LigneEtatDesLieux> =>
  envoyer<LigneEtatDesLieux>(`/backoffice/sejours/${id}/etats-des-lieux/${etatDesLieuId}/lignes`, { libelle, observation })

/** Rend la LIGNE mise à jour (pas l'état des lieux entier) : EtatsDesLieuxController::ajouterUnePhoto. */
export const ajouterUnePhotoDeLigne = (id: number, etatDesLieuId: number, ligneId: number, fichier: File): Promise<LigneEtatDesLieux> => {
  const formulaire = new FormData()
  formulaire.append('fichier', fichier)
  return envoyer<LigneEtatDesLieux>(`/backoffice/sejours/${id}/etats-des-lieux/${etatDesLieuId}/lignes/${ligneId}/photos`, formulaire)
}

/** `signature` : image signée à l'écran, encodée en base64 (SejoursController::signer — verrouille l'état des lieux). */
export const signerUnEtatDesLieux = (id: number, etatDesLieuId: number, signatureBase64: string): Promise<EtatDesLieux> =>
  envoyer<EtatDesLieux>(`/backoffice/sejours/${id}/etats-des-lieux/${etatDesLieuId}/signature`, { signature: signatureBase64 })
