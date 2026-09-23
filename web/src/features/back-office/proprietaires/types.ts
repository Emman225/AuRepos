export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type NatureJuridique = 'personne_physique' | 'entreprise'
export type RegimeFiscal = 'non_renseigne' | 'aucun' | 'entreprenant' | 'micro_entreprise' | 'reel_simplifie' | 'reel_normal'
export type ModeDeRemuneration = 'prix_negocie' | 'commission'
export type TypeDePiece = 'piece_identite' | 'titre_propriete' | 'bail' | 'rib' | 'mandat' | 'dfe' | 'attestation_regime' | 'rccm' | 'bilan' | 'autre'
export type StatutDePiece = 'en_attente' | 'validee' | 'refusee'

export interface PieceJustificative {
  id: number
  type: TypeDePiece
  type_libelle: string
  nom_original: string
  mime: string
  taille_octets: number
  statut: StatutDePiece
  statut_libelle: string
  motif_refus: string | null
  expire_le: string | null
  valable: boolean
  deposee_le: string | null
  verifiee_le: string | null
}

/** api/app/Http/Resources/Backoffice/ProprietaireResource.php. */
export interface Proprietaire {
  id: number
  nom_affiche: string
  interne: boolean
  compte: { id: number; nom: string; prenoms: string | null; email: string; telephone: string | null; statut: string }
  nature: NatureJuridique
  nature_libelle: string
  raison_sociale: string | null
  regime_fiscal: RegimeFiscal
  regime_fiscal_libelle: string
  assujetti_tva: boolean
  ncc: string | null
  rccm: string | null
  adresse: string | null
  mandat: {
    mode_remuneration: ModeDeRemuneration
    mode_remuneration_libelle: string
    taux_commission: number | null
    part_entreprise_cautions: number | null
    signe_le: string | null
    expire_le: string | null
    bons_valides_automatiquement: boolean
  }
  notes: string | null
  retenue_a_la_source: { taux: number; motif: string }
  dossier_complet: boolean
  elements_manquants: string[]
  nombre_residences?: number
  pieces?: PieceJustificative[]
}

/** POST/PUT .../proprietaires (ProprietaireRequest). */
export interface SaisieProprietaire {
  nom: string
  prenoms?: string
  email: string
  telephone?: string
  nature: NatureJuridique
  raison_sociale?: string
  regime_fiscal?: RegimeFiscal
  assujetti_tva?: boolean
  ncc?: string
  rccm?: string
  adresse?: string
  mode_remuneration?: ModeDeRemuneration
  taux_commission?: number
  part_entreprise_cautions?: number
  mandat_signe_le?: string
  mandat_expire_le?: string
  bons_valides_automatiquement?: boolean
  notes?: string
  interne?: boolean
}
