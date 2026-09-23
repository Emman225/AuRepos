import '@testing-library/jest-dom/vitest'
import { afterEach } from 'vitest'
import i18n from '../shared/i18n'

// i18next est un singleton : un test qui bascule la langue (P1-WEB-05) la laisserait sinon
// changée pour tous les tests suivants, même dans un autre fichier.
afterEach(() => {
  void i18n.changeLanguage('fr')
})
