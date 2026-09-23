import { api, envoyer, lire } from '../../shared/api/client'
import type { Devis, Sejour } from '../reservation/types'
import type {
  CommandeRepas,
  CompteATermeDetail,
  DemandeAnnulation,
  MesPaiements,
  MonCompte,
  PageDe,
  Reclamation,
  RestaurateurActif,
  SaisieCommandeRepas,
  SaisieDemandeTransfert,
  SaisieReclamation,
  SaisieTicketAssistance,
  TicketAssistance,
  TransfertClient,
} from './types'

export const mesSejours = (): Promise<PageDe<Sejour>> => lire<PageDe<Sejour>>('/client/sejours')

export const monSejour = (reference: string): Promise<Sejour> => lire<Sejour>(`/client/sejours/${reference}`)

export const annulerMonSejour = (reference: string): Promise<Sejour> =>
  envoyer<Sejour>(`/client/sejours/${reference}/annulation`)

export const mesDevis = (): Promise<PageDe<Devis>> => lire<PageDe<Devis>>('/client/devis')

export const monDevis = (reference: string): Promise<Devis> => lire<Devis>(`/client/devis/${reference}`)

export const archiverMonDevis = (reference: string): Promise<Devis> =>
  envoyer<Devis>(`/client/devis/${reference}`, undefined, 'delete')

export const mesPaiements = (): Promise<MesPaiements> => lire<MesPaiements>('/client/paiements')

/** Le jeton part comme pour tout appel (intercepteur) : une simple navigation `<a href>` ne le pourrait pas. */
export async function ouvrirMonRecu(numero: string): Promise<void> {
  const reponse = await api.get<Blob>(`/client/recus/${numero}`, { responseType: 'blob' })
  const url = URL.createObjectURL(reponse.data)
  window.open(url, '_blank')
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}

export const monCompte = (): Promise<MonCompte> => lire<MonCompte>('/client/compte')

export const monCompteATermeDetail = (): Promise<CompteATermeDetail> =>
  lire<CompteATermeDetail>('/client/compte/a-terme')

export const demanderLeCompteATerme = (): Promise<CompteATermeDetail> =>
  envoyer<CompteATermeDetail>('/client/compte/a-terme/demande')

export const deposerUnePieceATerme = (type: string, fichier: File): Promise<CompteATermeDetail> => {
  const formulaire = new FormData()
  formulaire.append('type', type)
  formulaire.append('fichier', fichier)
  return envoyer<CompteATermeDetail>('/client/compte/a-terme/pieces', formulaire)
}

/** Restaurateurs actifs et leur carte, pour savoir QUOI commander (CdC — « Commander repas et boissons pendant le séjour »). */
export const restaurateursActifs = (): Promise<RestaurateurActif[]> => lire<RestaurateurActif[]>('/client/restaurateurs')

export const mesCommandesRepas = (reference: string): Promise<CommandeRepas[]> =>
  lire<CommandeRepas[]>(`/client/sejours/${reference}/commandes`)

export const commanderUnRepas = (reference: string, saisie: SaisieCommandeRepas): Promise<CommandeRepas> =>
  envoyer<CommandeRepas>(`/client/sejours/${reference}/commandes`, saisie)

/** Mes extras et transferts (CdC § 5.2, § 6.6) : demande PENDANT un séjour déjà existant. */
export const mesTransfertsDuSejour = (reference: string): Promise<TransfertClient[]> =>
  lire<TransfertClient[]>(`/client/sejours/${reference}/transferts`)

export const demanderUnTransfert = (reference: string, saisie: SaisieDemandeTransfert): Promise<TransfertClient> =>
  envoyer<TransfertClient>(`/client/sejours/${reference}/transferts`, saisie)

/** Séjour confirmé (ou déjà arrivé) : une DEMANDE d'annulation, instruite par la réception (P2-SEJ-06). */
export const demanderLAnnulationDeMonSejour = (reference: string, motif: string): Promise<DemandeAnnulation> =>
  envoyer<DemandeAnnulation>(`/client/sejours/${reference}/demande-annulation`, { motif })

/** Assistance (P2-AST-01) : un ticket PENDANT un séjour en cours (« arrivé »). */
export const mesTicketsAssistance = (reference: string): Promise<TicketAssistance[]> =>
  lire<TicketAssistance[]>(`/client/sejours/${reference}/tickets-assistance`)

export const ouvrirUnTicketAssistance = (reference: string, saisie: SaisieTicketAssistance): Promise<TicketAssistance> =>
  envoyer<TicketAssistance>(`/client/sejours/${reference}/tickets-assistance`, saisie)

/** Réclamations (P2-AST-01) : APRÈS un séjour terminé, motif d'au moins 15 caractères. */
export const mesReclamations = (reference: string): Promise<Reclamation[]> =>
  lire<Reclamation[]>(`/client/sejours/${reference}/reclamations`)

export const creerUneReclamation = (reference: string, saisie: SaisieReclamation): Promise<Reclamation> =>
  envoyer<Reclamation>(`/client/sejours/${reference}/reclamations`, saisie)
