import { envoyer, lire } from '../../../shared/api/client'
import type { Mission, PageDe, SaisieDemandeMenage } from './types'

export const listerLesMissions = (filtres: Record<string, unknown> = {}): Promise<PageDe<Mission>> =>
  lire<PageDe<Mission>>('/backoffice/missions', filtres)

/** `agent_id` : identifiant du compte agent de terrain (aucun écran de sélection par nom n'existe encore côté API — voir notes de l'écran). */
export const affecterUnAgentALaMission = (id: number, agentId: number): Promise<Mission> =>
  envoyer<Mission>(`/backoffice/missions/${id}/affectation`, { agent_id: agentId }, 'put')

export const demanderUnMenage = (logementId: number, saisie: SaisieDemandeMenage): Promise<Mission> =>
  envoyer<Mission>(`/backoffice/logements/${logementId}/missions`, saisie)
