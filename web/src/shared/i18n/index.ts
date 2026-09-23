import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import en from './en.json'
import fr from './fr.json'
import { langueStockee } from './stockageLangue'

// Français par défaut ; l'anglais ne concerne que le site public et
// l'espace client (CdC § 13.2). Aucun libellé en dur dans les composants :
// Mon Gravier l'a fait, et la traduction y est devenue impraticable.
void i18n.use(initReactI18next).init({
  resources: { fr: { translation: fr }, en: { translation: en } },
  lng: langueStockee(),
  fallbackLng: 'fr',
  interpolation: { escapeValue: false },
})

export default i18n
