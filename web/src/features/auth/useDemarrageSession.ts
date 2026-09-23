import { useEffect } from 'react'
import { moi } from './api'
import { useSession } from './session'

/**
 * Au chargement, un jeton conservé ne prouve rien : on demande au serveur
 * qui il désigne. Un compte bloqué ou un jeton révoqué redevient anonyme.
 */
export function useDemarrageSession(): void {
  const statut = useSession((s) => s.statut)

  useEffect(() => {
    if (statut !== 'inconnu') return
    let actif = true

    moi()
      .then((utilisateur) => actif && useSession.getState().confirmer(utilisateur))
      .catch(() => actif && useSession.getState().fermer())

    return () => {
      actif = false
    }
  }, [statut])
}
