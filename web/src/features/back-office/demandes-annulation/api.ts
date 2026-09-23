import { envoyer, lire } from '../../../shared/api/client'
import type { DemandeAnnulation, PageDe } from './types'

export const listerLesDemandesAnnulation = (filtres: Record<string, unknown> = {}): Promise<PageDe<DemandeAnnulation>> =>
  lire<PageDe<DemandeAnnulation>>('/backoffice/demandes-annulation', filtres)

export const accepterUneDemandeAnnulation = (id: number, modeDeRemboursement?: string, motif?: string): Promise<DemandeAnnulation> =>
  envoyer<DemandeAnnulation>(`/backoffice/demandes-annulation/${id}/acceptation`, { mode_de_remboursement: modeDeRemboursement, motif })

/** `motif` est OBLIGATOIRE au rejet (DemandesAnnulationController), contrairement à l'acceptation. */
export const rejeterUneDemandeAnnulation = (id: number, motif: string): Promise<DemandeAnnulation> =>
  envoyer<DemandeAnnulation>(`/backoffice/demandes-annulation/${id}/rejet`, { motif })
