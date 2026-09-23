import { envoyer, lire } from '../../shared/api/client'
import type { Commande, CompteursRestaurateur, DetteRestaurateur, ProduitRepas, SaisieProduitRepas } from './types'

export const compteursDuRestaurateur = (): Promise<CompteursRestaurateur> => lire<CompteursRestaurateur>('/restaurateur/tableau-de-bord')

export const maCarte = (): Promise<ProduitRepas[]> => lire<ProduitRepas[]>('/restaurateur/produits')

export const ajouterUnProduit = (saisie: SaisieProduitRepas): Promise<ProduitRepas> => envoyer<ProduitRepas>('/restaurateur/produits', saisie)

export const modifierUnProduit = (id: number, saisie: Partial<SaisieProduitRepas>): Promise<ProduitRepas> =>
  envoyer<ProduitRepas>(`/restaurateur/produits/${id}`, saisie, 'put')

export const mesCommandes = (): Promise<Commande[]> => lire<Commande[]>('/restaurateur/commandes')

export const demarrerLaPreparation = (id: number): Promise<Commande> => envoyer<Commande>(`/restaurateur/commandes/${id}/preparation`)

/** Le bon de préparation : la quantité RÉELLEMENT servie par ligne (clé = id de la ligne). */
export const marquerLaCommandePrete = (id: number, quantitesServies: Record<number, number>): Promise<Commande> =>
  envoyer<Commande>(`/restaurateur/commandes/${id}/prete`, { quantites_servies: quantitesServies })

export const maDette = (): Promise<DetteRestaurateur> => lire<DetteRestaurateur>('/restaurateur/dette')
