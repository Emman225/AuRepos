import { create } from 'zustand'
import type { SessionOuverte, Utilisateur } from './types'

const CLE_JETON = 'residences.jeton'

/**
 * - `inconnu`  : au chargement, un jeton existe mais on n'a pas encore demandé au serveur qui c'est ;
 * - `connecte` : le serveur a confirmé le compte ;
 * - `anonyme`  : pas de jeton, ou jeton refusé.
 */
type Statut = 'inconnu' | 'connecte' | 'anonyme'

interface EtatSession {
  jeton: string | null
  utilisateur: Utilisateur | null
  statut: Statut
  ouvrir: (session: SessionOuverte) => void
  confirmer: (utilisateur: Utilisateur) => void
  remplacerJeton: (jeton: string) => void
  fermer: () => void
}

function lireJetonStocke(): string | null {
  try {
    return localStorage.getItem(CLE_JETON)
  } catch {
    return null // navigation privée stricte : on reste anonyme, sans planter
  }
}

function stockerJeton(jeton: string | null): void {
  try {
    if (jeton) localStorage.setItem(CLE_JETON, jeton)
    else localStorage.removeItem(CLE_JETON)
  } catch {
    /* stockage indisponible : la session vivra le temps de l'onglet */
  }
}

// Seul le jeton est conservé ; le compte est toujours redemandé au serveur,
// pour qu'un profil modifié ou un compte bloqué prenne effet au rechargement.
const jetonInitial = lireJetonStocke()

export const useSession = create<EtatSession>((set) => ({
  jeton: jetonInitial,
  utilisateur: null,
  statut: jetonInitial ? 'inconnu' : 'anonyme',

  ouvrir: ({ jeton, utilisateur }) => {
    stockerJeton(jeton)
    set({ jeton, utilisateur, statut: 'connecte' })
  },
  confirmer: (utilisateur) => set({ utilisateur, statut: 'connecte' }),
  remplacerJeton: (jeton) => {
    stockerJeton(jeton)
    set({ jeton })
  },
  fermer: () => {
    stockerJeton(null)
    set({ jeton: null, utilisateur: null, statut: 'anonyme' })
  },
}))
