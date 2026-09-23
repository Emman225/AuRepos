import { Spin } from 'antd'
import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useSession } from './session'
import type { Espace, Profil } from './types'

interface Props {
  /** L'espace auquel appartient cette branche de routes. */
  espace: Espace
  /** Restriction supplémentaire à certains profils de l'espace (ex. écrans réservés aux administrateurs). */
  profils?: Profil[]
  children: ReactNode
}

/**
 * Garde d'une branche de routes.
 *   - pas connecté      → page de connexion, en retenant l'adresse demandée ;
 *   - mauvais profil    → page 403 ;
 *   - session inconnue  → on attend la réponse du serveur, sans rien afficher de l'espace.
 *
 * C'est un confort d'interface : le serveur refuse de toute façon l'appel
 * d'un profil non autorisé (« masquer un menu n'est pas une protection »).
 */
export function RequireProfil({ espace, profils, children }: Props) {
  const { statut, utilisateur } = useSession()
  const lieu = useLocation()

  if (statut === 'inconnu') {
    return <Spin size="large" fullscreen />
  }

  if (statut === 'anonyme' || !utilisateur) {
    return <Navigate to="/connexion" replace state={{ retour: lieu.pathname + lieu.search }} />
  }

  const autorise = utilisateur.espace === espace && (!profils || profils.includes(utilisateur.profil))
  if (!autorise) {
    return <Navigate to="/acces-refuse" replace />
  }

  return <>{children}</>
}
