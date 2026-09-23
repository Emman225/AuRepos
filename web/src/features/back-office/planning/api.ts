import { envoyer, lire } from '../../../shared/api/client'
import type { BlocageDuPlanning, DonneesPlanning } from './types'

export interface FiltresPlanning {
  du: string
  au: string
  residence_id?: number
}

export const chargerLePlanning = (filtres: FiltresPlanning): Promise<DonneesPlanning> =>
  lire<DonneesPlanning>('/backoffice/planning', { ...filtres })

// ------------------------------------------------------------- Blocage de dates (P2-BO-01)
// Endpoint RÉEL, déjà en production côté fiche logement (api/app/Http/Controllers/Api/V1/
// Backoffice/CalendrierController.php) : réutilisé tel quel ici, aucun contrat inventé.

export const chargerLesBlocages = (residenceId: number, logementId: number): Promise<BlocageDuPlanning[]> =>
  lire<BlocageDuPlanning[]>(`/backoffice/residences/${residenceId}/logements/${logementId}/blocages`)

export const bloquerDesDates = (
  residenceId: number,
  logementId: number,
  saisie: { debut: string; fin: string; motif: string; commentaire?: string },
): Promise<BlocageDuPlanning> =>
  envoyer<BlocageDuPlanning>(`/backoffice/residences/${residenceId}/logements/${logementId}/blocages`, saisie)

export const debloquerDesDates = (residenceId: number, logementId: number, blocageId: number): Promise<null> =>
  envoyer<null>(`/backoffice/residences/${residenceId}/logements/${logementId}/blocages/${blocageId}`, undefined, 'delete')

// ------------------------------------------------------------- Déplacement d'un séjour (P2-PLA-02)
/**
 * AUCUN endpoint dédié n'existe encore côté API à la date d'écriture (23/09/2026) — seul
 * `GET /backoffice/planning` est en place, lecture seule (confirmé en lisant
 * `PlanningController.php` : pas de route `deplacer`/`deplacement`, aucun contrôleur du genre).
 * Chemin choisi par cohérence avec les autres sous-ressources d'un séjour déjà réelles (PUT
 * .../depart, voir `features/back-office/reservations/api.ts::modifierLeDepart`) — à corriger
 * si l'agent API retient un contrat différent pour P2-PLA-02. Tant que l'endpoint n'existe pas,
 * l'appel se solde par une erreur 404 traduite en message lisible par le client API (`ErreurApi`)
 * et affichée dans la modale : un échec visible, jamais silencieux.
 */
export const deplacerUnSejour = (sejourId: number, logementCibleId: number, motif: string): Promise<void> =>
  envoyer<void>(`/backoffice/sejours/${sejourId}/logement`, { logement_id: logementCibleId, motif }, 'put')
