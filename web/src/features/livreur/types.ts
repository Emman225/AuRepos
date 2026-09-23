export type EtatDeCommande = 'demande' | 'confirmee' | 'en_preparation' | 'prete' | 'en_livraison' | 'livree' | 'annulee' | 'refusee'

/** GET /livreur/tableau-de-bord. */
export interface CompteursLivreur {
  courses_en_cours: number
  courses_livrees: number
  gains: { total_gagne: number; deja_verse: number; solde_du: number }
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

/** Vue interne d'une commande — le code de livraison n'est JAMAIS lu ici, seulement SAISI (api/app/Http/Resources/Repas/CommandeResource.php). */
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

/** GET /livreur/gains — api/app/Domain/Repas/Services/GestionDesCommandes.php::gainsDuLivreur(). */
export interface GainsLivreur {
  total_gagne: number
  deja_verse: number
  solde_du: number
}
