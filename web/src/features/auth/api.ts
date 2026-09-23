import axios from 'axios'
import { api, brancherSession, envoyer, lire, type Enveloppe } from '../../shared/api/client'
import { useSession } from './session'
import type { SessionOuverte, Utilisateur } from './types'

export const connexion = (identifiant: string, motDePasse: string) =>
  envoyer<SessionOuverte>('/auth/connexion', { identifiant, mot_de_passe: motDePasse })

export const moi = () => lire<Utilisateur>('/auth/moi')

export async function deconnexion(): Promise<void> {
  try {
    await envoyer<null>('/auth/deconnexion') // révoque le jeton côté serveur
  } finally {
    useSession.getState().fermer() // même si le serveur est injoignable, on sort
  }
}

/**
 * Prolonge la session. Appel direct (hors intercepteurs) pour ne pas
 * reboucler sur un 401 ; l'ancien jeton part en liste noire côté serveur.
 */
async function rafraichirLeJeton(): Promise<string | null> {
  const ancien = useSession.getState().jeton
  if (!ancien) return null

  const { data } = await axios.post<Enveloppe<SessionOuverte>>(
    `${api.defaults.baseURL}/auth/rafraichir`,
    null,
    { headers: { Authorization: `Bearer ${ancien}`, Accept: 'application/json' } },
  )
  useSession.getState().remplacerJeton(data.data.jeton)
  return data.data.jeton
}

/** À appeler une fois, avant le premier rendu. */
export function relierSessionEtClient(): void {
  brancherSession(
    () => useSession.getState().jeton,
    () => useSession.getState().fermer(),
    rafraichirLeJeton,
  )
}

// ---- Inscription des clients, vérification du courriel, mot de passe oublié ----

export interface SaisieInscription {
  nom: string
  prenoms: string
  email: string
  telephone: string
  mot_de_passe: string
  mot_de_passe_confirmation: string
  conditions_acceptees: boolean
  code_parrain?: string
}

export const inscription = (saisie: SaisieInscription) =>
  envoyer<{ email: string; code_valable_minutes: number }>('/auth/inscription', saisie)

export const verifierLeCode = (email: string, code: string) => envoyer<SessionOuverte>('/auth/verification', { email, code })

export const renvoyerLeCode = (email: string) => envoyer<null>('/auth/verification/renvoyer', { email })

export const motDePasseOublie = (email: string) => envoyer<{ code_valable_minutes: number }>('/auth/mot-de-passe/oublie', { email })

export const reinitialiserLeMotDePasse = (saisie: {
  email: string
  code: string
  mot_de_passe: string
  mot_de_passe_confirmation: string
}) => envoyer<null>('/auth/mot-de-passe/reinitialiser', saisie)
