export const LANGUES = ['fr', 'en'] as const
export type Langue = (typeof LANGUES)[number]

const CLE_LANGUE = 'residences.langue'

/** Lue AVANT l'initialisation d'i18next : ne dépend d'aucun module i18n pour éviter tout cycle d'import. */
export function langueStockee(): Langue {
  try {
    return localStorage.getItem(CLE_LANGUE) === 'en' ? 'en' : 'fr'
  } catch {
    return 'fr' // navigation privée stricte : on reste en français, sans planter
  }
}

export function memoriserLangue(langue: Langue): void {
  try {
    localStorage.setItem(CLE_LANGUE, langue)
  } catch {
    /* stockage indisponible : le choix vivra le temps de l'onglet */
  }
}
