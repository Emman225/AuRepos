export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

/** api/app/Http/Resources/Repas/LigneDeCommandeResource.php. */
export interface LigneDeCommande {
  id: number
  produit_id: number
  nom_produit: string
  prix_unitaire_vente: number
  quantite_commandee: number
  quantite_servie: number | null
  montant: number
}

/** Vue interne d'une commande (back office, restaurateur, livreur) — api/app/Http/Resources/Repas/CommandeResource.php.
 * Ne montre jamais le code de livraison en clair, seulement `code_livraison_emis`. */
export interface Commande {
  id: number
  reference: string
  sejour_id: number
  sejour_reference: string | null
  restaurateur_id: number
  restaurateur: string | null
  etat: 'demande' | 'confirmee' | 'en_preparation' | 'prete' | 'en_livraison' | 'livree' | 'annulee' | 'refusee'
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
