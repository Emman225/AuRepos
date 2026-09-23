import { envoyer, lire } from '../../../shared/api/client'
import type { ChangementAValider, PageDe, Reclamation } from './types'

export const listerLesReclamations = (filtres: Record<string, unknown> = {}): Promise<PageDe<Reclamation>> =>
  lire<PageDe<Reclamation>>('/backoffice/reclamations', filtres)

export const fermerUneReclamation = (id: number, reponse?: string): Promise<Reclamation> =>
  envoyer<Reclamation>(`/backoffice/reclamations/${id}/fermeture`, { reponse })

/** Ne fait que PROPOSER (CdC § 6.4) : `avoir_montant` reste null tant que le trésorier n'a pas confirmé via la file des changements. */
export const proposerUnAvoir = (id: number, montant: number, motif: string): Promise<Reclamation> =>
  envoyer<Reclamation>(`/backoffice/reclamations/${id}/avoir`, { montant, motif })

/** File générique de double validation (`backoffice/changements`), en attente par défaut côté API. */
export const listerLesChangementsEnAttente = (): Promise<PageDe<ChangementAValider>> =>
  lire<PageDe<ChangementAValider>>('/backoffice/changements', { par_page: 100 })

export const deciderUnChangement = (
  id: number,
  decision: 'valider' | 'refuser' | 'annuler',
  motif?: string,
  modeDeRemboursement?: string,
): Promise<ChangementAValider> =>
  envoyer<ChangementAValider>(`/backoffice/changements/${id}/decision`, { decision, motif, mode_de_remboursement: modeDeRemboursement }, 'put')
