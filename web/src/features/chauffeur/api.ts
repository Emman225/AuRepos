import { envoyer, lire } from '../../shared/api/client'
import type { GainsChauffeur, SaisieVehiculeChauffeur, TableauDeBordChauffeur, TransfertChauffeur, VehiculeChauffeur } from './types'

export const tableauDeBordDuChauffeur = (): Promise<TableauDeBordChauffeur> => lire<TableauDeBordChauffeur>('/chauffeur/tableau-de-bord')

export const mesTransferts = (): Promise<TransfertChauffeur[]> => lire<TransfertChauffeur[]>('/chauffeur/transferts')

/**
 * Dérive l'identifiant numérique attendu par la route de clôture à partir de la référence
 * `TRF-000006` (format fixé par Transfert::booted(), voir le commentaire dans types.ts) : la
 * ressource de l'espace chauffeur n'expose pas d'`id`, contrairement à celle du back office.
 */
function idDepuisReference(reference: string): number {
  return parseInt(reference.replace(/^TRF-/, ''), 10)
}

export const cloturerUnTransfert = (reference: string, code: string): Promise<TransfertChauffeur> =>
  envoyer<TransfertChauffeur>(`/chauffeur/transferts/${idDepuisReference(reference)}/cloturer`, { code })

export const mesGains = (): Promise<GainsChauffeur> => lire<GainsChauffeur>('/chauffeur/gains')

export const mesVehicules = (): Promise<VehiculeChauffeur[]> => lire<VehiculeChauffeur[]>('/chauffeur/vehicules')

export const ajouterUnVehicule = (saisie: SaisieVehiculeChauffeur): Promise<VehiculeChauffeur> =>
  envoyer<VehiculeChauffeur>('/chauffeur/vehicules', saisie)
