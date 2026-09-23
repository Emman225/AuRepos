import { TeamOutlined, TransactionOutlined } from '@ant-design/icons'
import type { ReactNode } from 'react'

export interface EcranApporteur {
  /** Chemin relatif à la racine de l'espace (/apporteur). */
  chemin: string
  cle: string
  icone: ReactNode
}

/** Menu latéral de l'espace apporteur d'affaires (lecture seule — CdC, apporteurs). */
export const ECRANS_APPORTEUR: EcranApporteur[] = [
  { chemin: 'filleuls', cle: 'apporteur.menu.filleuls', icone: <TeamOutlined /> },
  { chemin: 'commissions', cle: 'apporteur.menu.commissions', icone: <TransactionOutlined /> },
]
