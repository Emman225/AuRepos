export interface NuiteeDevis {
  date: string
  saison: string | null
  tarif: number
  origine: string
}

export interface LigneMontant {
  libelle: string
  montant: number
}

/** DevisDeSejour::toArray() (api/app/Domain/Tarification/Calcul/DevisDeSejour.php) — même moteur pour l'estimation, la réservation et le devis. */
export interface SupplementDevis {
  code: string
  libelle: string
  quantite: number
  montant_unitaire: number
  montant: number
}

export interface ExtraDevis {
  libelle: string
  montant_ht: number
}

export interface TauxDuDevis {
  tva: number
  tva_transfert: number
  tdt: number
  taxe_sejour_montant: number
  taxe_sejour_base: string
}

export interface DevisDeSejour {
  nombre_de_nuits: number
  nuitees: NuiteeDevis[]
  hebergement_brut_ht: number
  supplements: SupplementDevis[]
  supplements_ht: number
  remise_pourcentage: number
  remise_ht: number
  reductions: LigneMontant[]
  reductions_ht: number
  reductions_plafonnees: boolean
  hebergement_net_ht: number
  extras: ExtraDevis[]
  extras_ht: number
  transfert_ht: number
  total_ht: number
  tva_hebergement_et_extras: number
  tva_transfert: number
  total_tva: number
  total_ttc: number
  tdt: number
  occupants_taxables: number
  taxe_de_sejour: number
  /** Case « autres taxes » de la facture normalisée : TDT + taxe de séjour. Ne jamais l'ajouter aux deux. */
  autres_taxes: number
  net_a_payer: number
  /** La caution n'est pas un produit : à part, jamais dans la base fiscale (CdC § 5.4). */
  caution: number
  total_avec_caution: number
  taux: TauxDuDevis
}

/** Ce que le client connecté pourrait retirer avec ses points sur ce séjour (PointsDeFidelite::utilisablesSur). */
export interface FideliteUtilisable {
  solde: number
  utilisables: number
  valeur: number
  valeur_du_point: number
  plafonne: boolean
}

/** POST /catalogue/logements/{reference}/estimation — le prix vient du serveur, à chaque frappe. */
export interface Estimation extends DevisDeSejour {
  disponible: boolean
  fidelite: FideliteUtilisable | null
  code_promo: { valide: boolean; motif: string | null }
}

export type ModeReglement = 'en_ligne' | 'agence' | 'a_terme'

export type TypeDePiece = 'cni' | 'passeport' | 'permis' | 'carte_consulaire' | 'autre'

export interface OccupantSaisi {
  nom: string
  prenoms?: string | null
  enfant?: boolean
  type_piece?: TypeDePiece | null
  numero_piece?: string | null
  telephone?: string | null
}

/** Des intentions, jamais un prix (api/app/Http/Requests/Tarification/EstimationRequest.php). */
export interface SaisieEstimation {
  arrivee: string
  depart: string
  adultes: number
  enfants?: number
  arrivee_tardive?: boolean
  depart_tardif?: boolean
  code_promo?: string
}

/** POST /client/devis (DevisRequest). */
export interface SaisieDevis extends SaisieEstimation {
  reference_logement: string
  heure_arrivee_prevue?: string
  points_utilises?: number
}

/** POST /client/sejours (ReservationRequest). */
export interface SaisieReservation extends SaisieDevis {
  mode_reglement: ModeReglement
  bon_de_commande?: string
  occupants?: OccupantSaisi[]
}

/** POST /client/devis/{reference}/transformation (TransformationDevisRequest). */
export interface SaisieTransformation {
  mode_reglement: ModeReglement
  bon_de_commande?: string
  occupants?: OccupantSaisi[]
}

interface LogementDuSejour {
  reference: string
  nom: string
  resume: string
  residence: string
  lieu: { commune: string; quartier: string }
}

/** Ce que le client voit de son séjour (api/app/Http/Resources/Client/SejourResource.php). */
export interface Sejour {
  reference: string
  etat: string
  etat_libelle: string
  arrivee: string
  depart: string
  nombre_de_nuits: number
  adultes: number
  enfants: number
  logement: LogementDuSejour
  /** Au client titulaire seul, et seulement une fois le séjour confirmé (CdC § 5.3). */
  code_d_arrivee: string | null
  /** L'adresse exacte n'est remise qu'après confirmation. */
  acces: { adresse: string | null; repere: string | null; consignes: string | null } | null
  mode_reglement: ModeReglement
  bon_de_commande: string | null
  /** Absent sur les séjours antérieurs à l'introduction du devis figé (colonne nullable côté API). */
  devis: DevisDeSejour | null
  net_a_payer: number
  caution: number
  acompte_exige: number
  points_utilises: number
  reduction_points: number
  expire_le: string | null
  annulation: {
    politique: string
    politique_libelle: string
    gratuite_jusqu_au: string
    pourcentage_retenu_ensuite: number
    retenu_si_annule_maintenant: number
    annule_le: string | null
    motif: string | null
    montant_retenu: number | null
  }
  occupants?: {
    nom: string
    prenoms: string | null
    enfant: boolean
    type_piece: string | null
    piece_fournie: boolean
  }[]
}

/** Ce que le client voit de son devis (api/app/Http/Resources/Client/DevisResource.php). */
export interface Devis {
  reference: string
  etat: 'en_attente' | 'transforme' | 'archive'
  etat_libelle: string
  arrivee: string
  depart: string
  nombre_de_nuits: number
  adultes: number
  enfants: number
  logement: LogementDuSejour
  code_promo: string | null
  devis: DevisDeSejour
  net_a_payer: number
  caution: number
  points_utilises: number
  reduction_points: number
  sejour?: string | null
  cree_le: string | null
}

export interface PaiementInitie {
  reference: string
  montant: number
  url_paiement: string
}

export interface EtatPaiement {
  reference: string
  etat: 'en_attente' | 'reussi' | 'echoue' | 'expire'
  montant: number
  sejour: string
  numero_recu: string | null
  motif: string | null
}

/** Éligibilité au règlement « à terme » (api/app/Http/Resources/ClientATermeResource.php) : seul le statut nous importe ici. */
export interface CompteATerme {
  statut: 'aucune' | 'en_attente' | 'acceptee' | 'refusee'
  statut_libelle: string
}
