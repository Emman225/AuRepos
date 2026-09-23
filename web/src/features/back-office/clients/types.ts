export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type StatutCompte = 'en_attente' | 'actif' | 'bloque'
export type NatureClient = 'b2c' | 'b2b' | 'b2g' | 'b2f'
export type StatutDemandeATerme = 'aucune' | 'en_attente' | 'acceptee' | 'refusee'

/** api/app/Http/Resources/Backoffice/ClientResource.php. */
export interface Client {
  id: number
  nom: string
  prenoms: string | null
  nom_complet: string
  email: string
  telephone: string | null
  statut: StatutCompte
  statut_libelle: string
  nature: NatureClient | null
  nature_libelle: string | null
  raison_sociale: string | null
  ncc: string | null
  rccm: string | null
  tva_hebergement: boolean | null
  tva_transfert: boolean | null
  tva_motif: string | null
  tva_motif_le: string | null
  statut_a_terme: StatutDemandeATerme | null
  statut_a_terme_libelle: string | null
  plafond_credit: number | null
  liste_noire: boolean
  liste_noire_motif: string | null
  liste_noire_le: string | null
  cree_le: string | null
}

export type TypeDePiece = 'piece_identite' | 'titre_propriete' | 'bail' | 'rib' | 'mandat' | 'dfe' | 'attestation_regime' | 'rccm' | 'bilan' | 'autre'
export type StatutDePiece = 'en_attente' | 'validee' | 'refusee'

export interface PieceJustificative {
  id: number
  type: TypeDePiece
  type_libelle: string
  nom_original: string
  statut: StatutDePiece
  statut_libelle: string
  motif_refus: string | null
  deposee_le: string | null
}

/** api/app/Http/Resources/ClientATermeResource.php. */
export interface ClientATerme {
  client_id: number
  utilisateur?: { id: number; nom: string; email: string; telephone: string | null }
  nature: NatureClient
  nature_libelle: string
  raison_sociale: string | null
  statut: StatutDemandeATerme
  statut_libelle: string
  plafond_credit: number | null
  encours: number | null
  demande_le: string | null
  motif_refus: string | null
  traite_le: string | null
  pieces?: PieceJustificative[]
}
