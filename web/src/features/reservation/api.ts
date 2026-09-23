import { envoyer, lire } from '../../shared/api/client'
import type {
  CompteATerme,
  Devis,
  Estimation,
  EtatPaiement,
  PaiementInitie,
  SaisieDevis,
  SaisieEstimation,
  SaisieReservation,
  SaisieTransformation,
  Sejour,
} from './types'

/**
 * Le total du tunnel : recalculé par le SERVEUR à chaque changement de la saisie
 * (CdC § 5.2). Route publique : un visiteur non connecté voit le même prix, sans
 * son tarif négocié ni ses points, qui supposent un compte.
 */
export const estimer = (reference: string, saisie: SaisieEstimation): Promise<Estimation> =>
  envoyer<Estimation>(`/catalogue/logements/${reference}/estimation`, saisie)

export const reserver = (saisie: SaisieReservation): Promise<Sejour> => envoyer<Sejour>('/client/sejours', saisie)

export const lireLeSejour = (reference: string): Promise<Sejour> => lire<Sejour>(`/client/sejours/${reference}`)

/** Devis autonome : les prix sont figés jusqu'à sa transformation (CdC § 5.1). */
export const etablirUnDevis = (saisie: SaisieDevis): Promise<Devis> => envoyer<Devis>('/client/devis', saisie)

export const transformerLeDevis = (reference: string, saisie: SaisieTransformation): Promise<Sejour> =>
  envoyer<Sejour>(`/client/devis/${reference}/transformation`, saisie)

/** Le client déclenche le paiement ; l'adresse de la passerelle vient du serveur. */
export const initierLePaiement = (reference: string, montant?: number): Promise<PaiementInitie> =>
  envoyer<PaiementInitie>(`/client/sejours/${reference}/paiement`, montant === undefined ? {} : { montant })

/** Retour de la passerelle : c'est le serveur qui redemande l'état réel, jamais le navigateur qui conclut. */
export const lireEtatPaiement = (reference: string): Promise<EtatPaiement> =>
  lire<EtatPaiement>(`/client/paiements/${reference}`)

/** Éligibilité au règlement « à terme » : n'affiche ce choix que si le dossier est accepté. */
export const monCompteATerme = (): Promise<CompteATerme> => lire<CompteATerme>('/client/compte/a-terme')
