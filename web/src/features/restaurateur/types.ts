export type CategorieProduit = 'plat' | 'boisson'
export type EtatDeCommande = 'demande' | 'confirmee' | 'en_preparation' | 'prete' | 'en_livraison' | 'livree' | 'annulee' | 'refusee'

/** GET /restaurateur/tableau-de-bord. */
export interface CompteursRestaurateur {
  commandes_par_etat: Record<EtatDeCommande, number>
  dette: { du: number; deja_verse: number }
}

/** api/app/Http/Resources/Repas/ProduitResource.php (vue interne : montre le prix d'achat). */
export interface ProduitRepas {
  id: number
  restaurateur_id: number
  nom: string
  description: string | null
  categorie: CategorieProduit
  prix_restaurateur: number
  prix_vente: number | null
  disponible: boolean
}

export interface SaisieProduitRepas {
  nom: string
  description?: string
  categorie: CategorieProduit
  prix_restaurateur: number
  disponible?: boolean
}

export interface LigneDeCommande {
  id: number
  produit_id: number
  nom_produit: string
  prix_unitaire_vente: number
  quantite_commandee: number
  quantite_servie: number | null
  montant: number
}

/** Vue interne d'une commande (jamais le code de livraison en clair) — api/app/Http/Resources/Repas/CommandeResource.php. */
export interface Commande {
  id: number
  reference: string
  sejour_id: number
  sejour_reference: string | null
  restaurateur_id: number
  restaurateur: string | null
  etat: EtatDeCommande
  etat_libelle: string
  mode_reglement: string
  montant_total: number
  livreur_id: number | null
  livreur: string | null
  remuneration_livreur: number | null
  code_livraison_emis: boolean
  notes: string | null
  lignes: LigneDeCommande[]
  cree_le: string | null
}

/** GET /restaurateur/dette. */
export interface DetteRestaurateur {
  du: number
  deja_verse: number
  paiements: { reference: string; montant: number; mode: string; date: string | null }[]
}
