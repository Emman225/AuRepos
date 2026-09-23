export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type EtatDuTicket = 'ouvert' | 'en_cours' | 'ferme'

/** api/app/Http/Resources/Assistance/TicketAssistanceResource.php. */
export interface TicketAssistance {
  id: number
  sejour: { reference: string; logement: string } | null
  client: string
  sujet: string
  message: string
  statut: EtatDuTicket
  statut_libelle: string
  reponse: string | null
  traite_le: string | null
  created_at: string | null
}
