import { CalendarOutlined, FileTextOutlined, UserOutlined, WalletOutlined } from '@ant-design/icons'
import type { ReactNode } from 'react'

export interface EcranClient {
  /** Chemin relatif à la racine de l'espace (/mon-espace). */
  chemin: string
  /** Clé i18n du libellé de menu. */
  cle: string
  icone: ReactNode
}

/** Menu latéral de l'espace client (CdC § 5.3). */
export const ECRANS_CLIENT: EcranClient[] = [
  { chemin: 'sejours', cle: 'client.menu.sejours', icone: <CalendarOutlined /> },
  { chemin: 'devis', cle: 'client.menu.devis', icone: <FileTextOutlined /> },
  { chemin: 'paiements', cle: 'client.menu.paiements', icone: <WalletOutlined /> },
  { chemin: 'compte', cle: 'client.menu.compte', icone: <UserOutlined /> },
]
