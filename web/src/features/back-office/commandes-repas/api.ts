import { envoyer, lire } from '../../../shared/api/client'
import type { Commande, PageDe } from './types'

export const listerLesCommandesRepas = (filtres: Record<string, unknown> = {}): Promise<PageDe<Commande>> =>
  lire<PageDe<Commande>>('/backoffice/commandes-repas', filtres)

export const confirmerLaCommande = (id: number): Promise<Commande> =>
  envoyer<Commande>(`/backoffice/commandes-repas/${id}/confirmer`)

export const affecterUnLivreurALaCommande = (id: number, livreurId: number, remunerationLivreur: number): Promise<Commande> =>
  envoyer<Commande>(`/backoffice/commandes-repas/${id}/affecter-livreur`, { livreur_id: livreurId, remuneration_livreur: remunerationLivreur })

export const refuserLaCommande = (id: number, motif: string): Promise<Commande> =>
  envoyer<Commande>(`/backoffice/commandes-repas/${id}/refuser`, { motif })
