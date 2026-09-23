import dayjs from 'dayjs'
import 'dayjs/locale/en'
import 'dayjs/locale/fr'
import i18n from './index'
import { langueStockee, memoriserLangue } from './stockageLangue'
import type { Langue } from './stockageLangue'

export { LANGUES } from './stockageLangue'
export type { Langue } from './stockageLangue'

function appliquerAuDocument(langue: Langue): void {
  dayjs.locale(langue)
  document.documentElement.lang = langue
}

/** Bascule fr / en : traductions, format des dates (dayjs) et <html lang>, mémorisée pour la prochaine visite. */
export async function definirLangue(langue: Langue): Promise<void> {
  await i18n.changeLanguage(langue)
  appliquerAuDocument(langue)
  memoriserLangue(langue)
}

// Applique tout de suite la langue déjà mémorisée (avant le premier rendu de l'application).
appliquerAuDocument(langueStockee())
