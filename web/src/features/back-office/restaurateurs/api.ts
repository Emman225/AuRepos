import { envoyer, lire } from '../../../shared/api/client'
import type {
  ChangementAValider,
  DetteRestaurateur,
  PageDe,
  ProduitRepas,
  Restaurateur,
  SaisieProduitRepas,
  SaisieRestaurateur,
} from './types'

export const listerLesRestaurateurs = (filtres: Record<string, unknown> = {}): Promise<PageDe<Restaurateur>> =>
  lire<PageDe<Restaurateur>>('/backoffice/restaurateurs', filtres)

export const creerUnRestaurateur = (saisie: SaisieRestaurateur): Promise<Restaurateur> =>
  envoyer<Restaurateur>('/backoffice/restaurateurs', saisie)

export const modifierLeRestaurateur = (id: number, saisie: Partial<SaisieRestaurateur>): Promise<Restaurateur> =>
  envoyer<Restaurateur>(`/backoffice/restaurateurs/${id}`, saisie, 'put')

/** Double validation : n'entre en vigueur qu'après décision d'un second administrateur (file « changements »). */
export const proposerLePourcentagePlateforme = (id: number, pourcentage: number, motif?: string): Promise<ChangementAValider> =>
  envoyer<ChangementAValider>(`/backoffice/restaurateurs/${id}/pourcentage/proposer`, { pourcentage, motif })

export const detteDuRestaurateur = (id: number): Promise<DetteRestaurateur> =>
  lire<DetteRestaurateur>(`/backoffice/restaurateurs/${id}/dette`)

export const laCarteDuRestaurateur = (restaurateurId: number): Promise<ProduitRepas[]> =>
  lire<ProduitRepas[]>(`/backoffice/restaurateurs/${restaurateurId}/produits`)

export const ajouterUnProduitALaCarte = (restaurateurId: number, saisie: SaisieProduitRepas): Promise<ProduitRepas> =>
  envoyer<ProduitRepas>(`/backoffice/restaurateurs/${restaurateurId}/produits`, saisie)

export const modifierUnProduitDeLaCarte = (restaurateurId: number, produitId: number, saisie: Partial<SaisieProduitRepas>): Promise<ProduitRepas> =>
  envoyer<ProduitRepas>(`/backoffice/restaurateurs/${restaurateurId}/produits/${produitId}`, saisie, 'put')
