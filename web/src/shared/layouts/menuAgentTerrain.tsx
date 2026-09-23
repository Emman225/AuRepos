import { CalendarOutlined, ToolOutlined } from '@ant-design/icons'
import type { ReactNode } from 'react'

export interface EcranAgentTerrain {
  /** Chemin relatif à la racine de l'espace (/agent). */
  chemin: string
  cle: string
  icone: ReactNode
}

/** Menu latéral de l'espace agent de terrain (self-service, CdC § 6.3, P2-MOB-04) — même patron que menuChauffeur/menuLivreur. */
export const ECRANS_AGENT_TERRAIN: EcranAgentTerrain[] = [
  { chemin: 'sejours', cle: 'agent.menu.sejours', icone: <CalendarOutlined /> },
  { chemin: 'missions', cle: 'agent.menu.missions', icone: <ToolOutlined /> },
]
