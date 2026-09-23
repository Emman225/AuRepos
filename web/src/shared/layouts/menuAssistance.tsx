import type { ReactNode } from 'react'

export interface EcranAssistance {
  /** Chemin relatif à la racine de l'espace (/assistance). */
  chemin: string
  cle: string
  icone: ReactNode
}

/**
 * Menu latéral de l'espace assistance (self-service, CdC § 6.1, P2-AST-01) : volontairement vide
 * — la file de tickets EST le tableau de bord (features/assistance/PageTableauDeBord.tsx), aucun
 * second écran ne le justifie encore. Seule l'entrée « Tableau de bord » générique (GabaritEspace)
 * apparaît dans le menu, comme pour tout espace.
 */
export const ECRANS_ASSISTANCE: EcranAssistance[] = []
