export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type TypeDeFacture = 'proforma' | 'facture' | 'avoir'
export type StatutDeTransmissionFne = 'a_transmettre' | 'transmise' | 'refusee' | 'non_configuree'

export interface LigneDeFacture {
  description: string
  quantity: number
  amount: number
  taxes: string[]
}

/** api/app/Http/Resources/Backoffice/FactureResource.php. */
export interface Facture {
  id: number
  numero: string
  type: TypeDeFacture
  type_libelle: string
  sejour?: { id: number; reference: string; arrivee: string; depart: string }
  client?: { id: number; nom: string; email: string }
  facture_origine?: { id: number; numero: string } | null
  motif_avoir: string | null
  montant_ht: number
  montant_tva: number
  autres_taxes: number
  montant_ttc: number
  lignes: LigneDeFacture[]
  statut_transmission: StatutDeTransmissionFne
  statut_transmission_libelle: string
  reference_dgi: string | null
  token_qr: string | null
  ncc_dgi: string | null
  solde_stickers: number | null
  motif_refus_dgi: string | null
  transmise_par: string | null
  transmise_le: string | null
  cree_le: string | null
}
