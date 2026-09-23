export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

export type TypeDeParametre = 'entier' | 'decimal' | 'booleen' | 'texte' | 'texte_long' | 'heure' | 'liste' | 'administrateur'

/** Un paramètre de l'onglet, tel que rendu par api/app/Domain/Parametres/Services/Parametres.php::onglets(). */
export interface ParametreValeur {
  cle: string
  nom: string
  libelle: string
  type: TypeDeParametre
  valeur: unknown
  defaut: unknown
  options: Record<string, string> | null
  aide: string | null
  reserve_super_administrateur: boolean
  double_validation: boolean
}

export interface Onglet {
  code: string
  libelle: string
  parametres: ParametreValeur[]
}

export interface AdministrateurChoix {
  id: number
  nom: string
}

/** GET /backoffice/parametres. */
export interface DonneesParametres {
  onglets: Onglet[]
  administrateurs: AdministrateurChoix[]
  alertes: string[]
}

// ---------------------------------------------------------------- Divers (P1-BO-10)

export type StatutArticle = 'brouillon' | 'publie'

/** api/app/Http/Resources/Backoffice/ArticleResource.php. */
export interface Article {
  id: number
  titre: string
  slug: string
  resume: string | null
  contenu: string
  image_url: string | null
  statut: StatutArticle
  statut_libelle: string
  publie_le: string | null
  auteur: { id: number; nom: string } | null
  cree_le: string | null
}

export interface SaisieArticle {
  titre?: string
  resume?: string
  contenu?: string
  image_url?: string
  statut?: StatutArticle
}

/** api/app/Http/Resources/Backoffice/BanniereResource.php. */
export interface Banniere {
  id: number
  titre: string
  sous_titre: string | null
  image_url: string
  lien: string | null
  ordre: number
  actif: boolean
  cree_le: string | null
}

export interface SaisieBanniere {
  titre?: string
  sous_titre?: string
  image_url?: string
  lien?: string
  ordre?: number
  actif?: boolean
}

/** api/app/Http/Resources/Backoffice/DiapositiveResource.php. */
export interface Diapositive {
  id: number
  image_url: string
  legende: string | null
  lien: string | null
  ordre: number
  actif: boolean
  cree_le: string | null
}

export interface SaisieDiapositive {
  image_url?: string
  legende?: string
  lien?: string
  ordre?: number
  actif?: boolean
}

/** api/app/Http/Resources/Backoffice/AbonneNewsletterResource.php. */
export interface AbonneNewsletter {
  id: number
  email: string
  nom: string | null
  actif: boolean
  abonne_le: string | null
  desabonne_le: string | null
}
