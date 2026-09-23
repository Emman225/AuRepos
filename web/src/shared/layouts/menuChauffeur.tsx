import { CarOutlined, CarryOutOutlined, WalletOutlined } from '@ant-design/icons'
import type { ReactNode } from 'react'

export interface EcranChauffeur {
  /** Chemin relatif à la racine de l'espace (/chauffeur). */
  chemin: string
  cle: string
  icone: ReactNode
}

/** Menu latéral de l'espace chauffeur (self-service, CdC § 6.6) — même patron que menuProprietaire/menuApporteur. */
export const ECRANS_CHAUFFEUR: EcranChauffeur[] = [
  { chemin: 'transferts', cle: 'chauffeur.menu.transferts', icone: <CarryOutOutlined /> },
  { chemin: 'gains', cle: 'chauffeur.menu.gains', icone: <WalletOutlined /> },
  { chemin: 'vehicules', cle: 'chauffeur.menu.vehicules', icone: <CarOutlined /> },
]
