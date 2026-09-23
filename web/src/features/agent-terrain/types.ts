/**
 * Espace agent de terrain (self-service, CdC § 6.3, P2-MOB-04) : routes/api_v1/agent.php rend
 * EXACTEMENT les mêmes ressources que le back office pour un séjour (`Backoffice\SejourResource`,
 * `Sejours\OccupantResource`, `Sejours\EtatDesLieuxResource`…) et pour une mission
 * (`Exploitation\MissionResource`) — réutilisées telles quelles plutôt que dupliquées, l'agent
 * voyant exactement les mêmes champs que le back office (même `piece_fournie` jamais le numéro
 * de pièce, CdC § 11).
 */
export type {
  ReservationBackOffice as SejourAgent,
  OccupantDeSejour,
  ConsommationsDuSejour,
  EtatDesLieux,
  LigneEtatDesLieux,
  TypeEtatDesLieux,
} from '../back-office/reservations/types'

export type { Mission, StatutDeMission } from '../back-office/missions/types'
