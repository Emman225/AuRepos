import { CarOutlined, WalletOutlined } from '@ant-design/icons'
import type { ReactNode } from 'react'

export interface EcranLivreur {
  /** Chemin relatif à la racine de l'espace (/livreur). */
  chemin: string
  cle: string
  icone: ReactNode
}

/** Menu latéral de l'espace livreur (self-service — CdC « Repas et boissons », espace livreur). */
export const ECRANS_LIVREUR: EcranLivreur[] = [
  { chemin: 'courses', cle: 'livreur.menu.courses', icone: <CarOutlined /> },
  { chemin: 'gains', cle: 'livreur.menu.gains', icone: <WalletOutlined /> },
]
