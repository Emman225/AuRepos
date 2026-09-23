export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type EtatDeLaDemandeAnnulation = 'en_attente' | 'acceptee' | 'rejetee'

/** api/app/Http/Resources/Sejours/DemandeAnnulationResource.php. */
export interface DemandeAnnulation {
  id: number
  sejour: { reference: string; arrivee: string; depart: string; net_a_payer: number; etat: string }
  client: string
  motif_client: string
  etat: EtatDeLaDemandeAnnulation
  etat_libelle: string
  montant_retenu: number | null
  montant_rembourse: number | null
  motif_decision: string | null
  instruite_le: string | null
  created_at: string | null
}
