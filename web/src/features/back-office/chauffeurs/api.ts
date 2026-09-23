import { envoyer, lire } from '../../../shared/api/client'
import type { Chauffeur, PageDe, SaisieChauffeur, SaisieVehicule, Vehicule } from './types'

export const listerLesChauffeurs = (filtres: Record<string, unknown> = {}): Promise<PageDe<Chauffeur>> =>
  lire<PageDe<Chauffeur>>('/backoffice/chauffeurs', filtres)

export const creerUnChauffeur = (saisie: SaisieChauffeur): Promise<Chauffeur> => envoyer<Chauffeur>('/backoffice/chauffeurs', saisie)

export const modifierLeChauffeur = (id: number, saisie: Partial<SaisieChauffeur>): Promise<Chauffeur> =>
  envoyer<Chauffeur>(`/backoffice/chauffeurs/${id}`, saisie, 'put')

export const vehiculesDuChauffeur = (chauffeurId: number): Promise<Vehicule[]> =>
  lire<Vehicule[]>(`/backoffice/chauffeurs/${chauffeurId}/vehicules`)

export const creerUnVehicule = (chauffeurId: number, saisie: SaisieVehicule): Promise<Vehicule> =>
  envoyer<Vehicule>(`/backoffice/chauffeurs/${chauffeurId}/vehicules`, saisie)

export const modifierUnVehicule = (chauffeurId: number, vehiculeId: number, saisie: Partial<SaisieVehicule>): Promise<Vehicule> =>
  envoyer<Vehicule>(`/backoffice/chauffeurs/${chauffeurId}/vehicules/${vehiculeId}`, saisie, 'put')
