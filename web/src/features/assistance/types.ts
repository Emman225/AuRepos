/**
 * Espace assistance (self-service, CdC § 6.1, P2-AST-01) : même ressource que le back office
 * (`Assistance\TicketAssistanceResource`, routes/api_v1/assistance.php), réutilisée telle quelle
 * plutôt que dupliquée — le back office ne fait QUE consulter (voir
 * features/back-office/tickets-assistance), répondre et fermer un ticket se font ici.
 */
export type { TicketAssistance, EtatDuTicket } from '../back-office/tickets-assistance/types'
