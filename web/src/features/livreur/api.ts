import { envoyer, lire } from '../../shared/api/client'
import type { Commande, CompteursLivreur, GainsLivreur } from './types'

export const compteursDuLivreur = (): Promise<CompteursLivreur> => lire<CompteursLivreur>('/livreur/tableau-de-bord')

export const mesCourses = (): Promise<Commande[]> => lire<Commande[]>('/livreur/courses')

/** Le livreur SAISIT le code que le client lui remet en main propre ; il ne le lit jamais à l'avance. */
export const cloturerUneCourse = (id: number, code: string): Promise<Commande> =>
  envoyer<Commande>(`/livreur/courses/${id}/cloture`, { code })

export const mesGains = (): Promise<GainsLivreur> => lire<GainsLivreur>('/livreur/gains')
