import { HomeOutlined } from '@ant-design/icons'
import type { ReactNode } from 'react'

export interface EcranProprietaire {
  /** Chemin relatif à la racine de l'espace (/proprietaire). */
  chemin: string
  cle: string
  icone: ReactNode
}

/** Menu latéral de l'espace propriétaire (lecture seule, P3-PRO anticipé partiellement). */
export const ECRANS_PROPRIETAIRE: EcranProprietaire[] = [
  { chemin: 'residences', cle: 'proprietaire.menu.residences', icone: <HomeOutlined /> },
]
