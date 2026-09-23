import { ShoppingCartOutlined, TagsOutlined, WalletOutlined } from '@ant-design/icons'
import type { ReactNode } from 'react'

export interface EcranRestaurateur {
  /** Chemin relatif à la racine de l'espace (/restaurateur). */
  chemin: string
  cle: string
  icone: ReactNode
}

/** Menu latéral de l'espace restaurateur (self-service — CdC « Repas et boissons », espace restaurateur). */
export const ECRANS_RESTAURATEUR: EcranRestaurateur[] = [
  { chemin: 'carte', cle: 'restaurateur.menu.carte', icone: <TagsOutlined /> },
  { chemin: 'commandes', cle: 'restaurateur.menu.commandes', icone: <ShoppingCartOutlined /> },
  { chemin: 'dette', cle: 'restaurateur.menu.dette', icone: <WalletOutlined /> },
]
