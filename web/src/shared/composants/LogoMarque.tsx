import { useTranslation } from 'react-i18next'
import { couleurs } from '../theme/jetons'

interface Props {
  /** Hauteur du logo en pixels. */
  taille?: number
  /**
   * Le logo est dessiné pour un fond clair (texte et maison en bleu nuit) : sur un fond
   * sombre (en-têtes, menus), on le pose sur une pastille blanche plutôt que de le laisser
   * se fondre dans le fond. Sur un fond déjà clair, il se pose directement, sans pastille.
   */
  surFondSombre?: boolean
  /**
   * Le fichier complet (maison + « Au Repos » + slogan) est un pavé carré : en dessous
   * d'une quarantaine de pixels de haut, le texte devient illisible. `icone` recadre sur
   * le seul pictogramme (favicon.png) pour les en-têtes et menus étroits ; `complet` garde
   * le pavé entier pour les endroits qui ont la hauteur (connexion, pied de page).
   */
  variante?: 'icone' | 'complet'
}

/** Logo « Au Repos » (fourni par le client) : un seul point d'entrée pour toutes les tailles/fonds. */
export function LogoMarque({ taille = 36, surFondSombre = false, variante = 'complet' }: Props) {
  const { t } = useTranslation()
  const image = (
    <img src={variante === 'icone' ? '/favicon.png' : '/logo.png'} alt={t('marque')} style={{ height: taille, display: 'block' }} />
  )

  if (!surFondSombre) return image

  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        background: couleurs.blanc,
        borderRadius: 999,
        padding: '4px 10px',
      }}
    >
      {image}
    </span>
  )
}
