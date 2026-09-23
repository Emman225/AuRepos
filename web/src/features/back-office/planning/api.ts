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
 * `PUT /backoffice/sejours/{sejour}/logement` (P2-PLA-02), contrat vérifié contre
 * `SejoursController::deplacer` : corps `{ logement_id, motif }`, motif d'au moins 5 caractères.
 * La contrainte « uniquement vers un logement du MÊME type » est appliquée côté serveur
 * (`App\Domain\Sejours\Services\DeplacementDeSejour`), qui refuse en 422 avec un message clair :
 * l'écran ne la duplique pas, `PlanningLogementResource` n'exposant pas le type de logement.
 */
export const deplacerUnSejour = (sejourId: number, logementCibleId: number, motif: string): Promise<void> =>
  envoyer<void>(`/backoffice/sejours/${sejourId}/logement`, { logement_id: logementCibleId, motif }, 'put')
