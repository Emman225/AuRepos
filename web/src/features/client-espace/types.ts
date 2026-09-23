import type { CompteATerme } from '../reservation/types'

export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

interface RegimeClient {
  nom: string
  prenoms: string | null
  nom_complet: string
  email: string
  telephone: string | null
  nature: 'b2c' | 'b2b' | 'b2g' | 'b2f'
  nature_libelle: string
  raison_sociale: string | null
  ncc: string | null
  tva_hebergement: boolean
  tva_transfert: boolean
}

/** GET /client/compte (api/app/Http/Controllers/Api/V1/Client/CompteController.php::monCompte). */
export type MonCompte = RegimeClient

export interface PieceATerme {
  id: number
  type: string
  type_libelle: string
  statut: string
  statut_libelle: string
  nom_original: string
  motif_refus: string | null
  deposee_le: string | null
}

/** GET /client/compte/a-terme — version complète (api/app/Http/Resources/ClientATermeResource.php). */
export interface CompteATermeDetail extends CompteATerme {
  client_id: number
  nature: string
  nature_libelle: string
  raison_sociale: string | null
  plafond_credit: number
  encours: number | null
  demande_le: string | null
  motif_refus: string | null
  traite_le: string | null
  pieces?: PieceATerme[]
}

export interface Reglement {
  reference: string
  numero_recu: string | null
  date: string | null
  montant: number
  mode: string
  affaires: string[]
}

export interface MouvementPoints {
  date: string | null
  nature: string
  points: number
  libelle: string
  valeur: number
}

/** api/app/Domain/Fidelite/Services/PointsDeFidelite.php::releve(). */
export interface ReleveFidelite {
  solde: number
  valeur: number
  valeur_du_point: number
  montant_par_point: number
  mouvements: MouvementPoints[]
}

/** GET /client/paiements (api/app/Http/Controllers/Api/V1/Client/SejoursController.php::paiements). */
export interface MesPaiements {
  avance_disponible: number
  montant_a_regler_en_agence: number
  fidelite: ReleveFidelite
  reglements: Reglement[]
}

export type CategorieProduitRepas = 'plat' | 'boisson'

/** api/app/Http/Resources/Client/Repas/ProduitResource.php — jamais le prix restaurateur. */
export interface ProduitDeLaCarte {
  id: number
  nom: string
  description: string | null
  categorie: CategorieProduitRepas
  prix: number
  disponible: boolean
}

/** GET /client/restaurateurs — api/app/Http/Resources/Client/Repas/RestaurateurResource.php. */
export interface RestaurateurActif {
  id: number
  nom: string
  carte: ProduitDeLaCarte[]
}

export interface LigneDeCommandeSaisie {
  produit_id: number
  quantite: number
}

export type ModeDeReglementRepas = 'en_ligne' | 'note_du_sejour' | 'a_terme'

export interface SaisieCommandeRepas {
  restaurateur_id: number
  mode_reglement: ModeDeReglementRepas
  notes?: string
  lignes: LigneDeCommandeSaisie[]
}

export interface LigneDeCommandeRepas {
  id: number
  produit_id: number
  nom_produit: string
  prix_unitaire_vente: number
  quantite_commandee: number
  quantite_servie: number | null
  montant: number
}

/** GET /client/sejours/{reference}/commandes — api/app/Http/Resources/Client/Repas/CommandeResource.php.
 * `code_livraison` n'apparaît que lorsque la commande est EN LIVRAISON. */
export interface CommandeRepas {
  id: number
  reference: string
  restaurateur: string | null
  etat: 'demande' | 'confirmee' | 'en_preparation' | 'prete' | 'en_livraison' | 'livree' | 'annulee' | 'refusee'
  etat_libelle: string
  mode_reglement: string
  montant_total: number
  code_livraison: string | null
  lignes: LigneDeCommandeRepas[]
  cree_le: string | null
}

/**
 * GET /client/sejours/{reference}/transferts — api/app/Http/Resources/Client/TransfertResource.php.
 * `code_prise_en_charge` n'apparaît en clair que lorsque le transfert est affecté (CdC § 11) ;
 * `null` tant qu'il n'a pas encore été émis.
 */
export interface TransfertClient {
  reference: string
  lieu_de_prise_en_charge: string
  commune: string | null
  type_vehicule_souhaite: string | null
  date_heure_prevue: string
  nombre_passagers: number
  nombre_bagages: number
  montant: number
  etat: string
  etat_libelle: string
  code_prise_en_charge: string | null
}

/** POST /client/sejours/{reference}/transferts (Client\TransfertsController::demander). */
export interface SaisieDemandeTransfert {
  lieu_de_prise_en_charge: string
  commune_id: number
  type_vehicule_souhaite_id: number
  date_heure_prevue: string
  nombre_passagers: number
  nombre_bagages?: number
  notes?: string
}
