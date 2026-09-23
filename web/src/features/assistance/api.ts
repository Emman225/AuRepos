import { envoyer, lire } from '../../shared/api/client'
import type { EtatDuTicket, TicketAssistance } from './types'

/** GET /assistance/tickets (TicketsController::index) : sans filtre, seulement les ouverts + en cours ; `statut` force un filtre explicite. */
export const mesTickets = (filtres: { statut?: EtatDuTicket } = {}): Promise<TicketAssistance[]> =>
  lire<TicketAssistance[]>('/assistance/tickets', filtres)

export const repondreAuTicket = (id: number, reponse: string): Promise<TicketAssistance> =>
  envoyer<TicketAssistance>(`/assistance/tickets/${id}/reponse`, { reponse })

export const fermerLeTicket = (id: number, reponse?: string): Promise<TicketAssistance> =>
  envoyer<TicketAssistance>(`/assistance/tickets/${id}/fermeture`, { reponse })
