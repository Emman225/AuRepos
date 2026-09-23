export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

/** api/app/Http/Resources/Repas/RestaurateurResource.php. */
export interface Restaurateur {
  id: number
  actif: boolean
  assujetti_tva: boolean
  pourcentage_plateforme: number | null
  compte: {
    id: number
    nom: string
    prenoms: string | null
    nom_complet: string
    email: string
    telephone: string | null
    statut: string
  }
}

/** POST/PUT /backoffice/restaurateurs — `pourcentage_plateforme` n'y figure jamais (double validation). */
export interface SaisieRestaurateur {
  nom: string
  prenoms?: string
  email: string
  telephone?: string
  assujetti_tva?: boolean
  actif?: boolean
}

export type CategorieProduit = 'plat' | 'boisson'

/** api/app/Http/Resources/Repas/ProduitResource.php. */
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

/** api/app/Domain/Repas/Services/GestionDesCommandes.php::detteEnversLeRestaurateur(). */
export interface DetteRestaurateur {
  du: number
  deja_verse: number
}

/** POST /backoffice/restaurateurs/{id}/pourcentage/proposer (double validation, App\Http\Resources\Backoffice\ChangementAValiderResource). */
export interface ChangementAValider {
  id: number
  sujet: string | null
  sujet_type: string | null
  sujet_id: number | null
  champ: string
  valeur_actuelle: string | null
  valeur_proposee: string | null
  motif: string | null
  statut: 'en_attente' | 'valide' | 'refuse' | 'annule'
  propose_par: string
  propose_le: string | null
  decide_par: string | null
  decide_le: string | null
  motif_decision: string | null
  je_peux_valider: boolean
  je_peux_annuler: boolean
}
