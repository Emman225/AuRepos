import type { Espace } from './types'

/**
 * Adresse d'accueil de chaque espace. La redirection s'applique à la
 * connexion ET au chargement : quelqu'un qui tape une adresse qui n'est pas
 * la sienne est renvoyé chez lui.
 *
 * Ce n'est qu'un confort d'affichage : le vrai cloisonnement est côté
 * serveur (middleware `profil`), qui refuse l'appel quoi que fasse le navigateur.
 */
export const ACCUEIL_PAR_ESPACE: Record<Espace, string> = {
  backoffice: '/admin',
  assistance: '/assistance',
  proprietaire: '/proprietaire',
  agent: '/agent',
  chauffeur: '/chauffeur',
  livreur: '/livreur',
  restaurateur: '/restaurateur',
  apporteur: '/apporteur',
  client: '/mon-espace',
}

export const accueilDe = (espace: Espace): string => ACCUEIL_PAR_ESPACE[espace] ?? '/'
