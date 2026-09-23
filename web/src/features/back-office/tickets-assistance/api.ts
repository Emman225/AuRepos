import { lire } from '../../../shared/api/client'
import type { PageDe, TicketAssistance } from './types'

export const listerLesTicketsAssistance = (filtres: Record<string, unknown> = {}): Promise<PageDe<TicketAssistance>> =>
  lire<PageDe<TicketAssistance>>('/backoffice/tickets-assistance', filtres)
