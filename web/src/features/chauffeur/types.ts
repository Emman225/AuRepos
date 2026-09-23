/** GET /chauffeur/tableau-de-bord (TableauDeBordController::index + GestionDesTransferts::mesGains). */
export interface TableauDeBordChauffeur {
  nombre_transferts_affectes: number
  total_gagne: number
  solde_du: number
}

/**
 * api/app/Http/Resources/Chauffeur/TransfertResource.php.
 * NB : cette ressource n'expose PAS d'`id` numérique (seulement `reference`), alors que la
 * route de clôture (`POST /chauffeur/transferts/{transfert}/cloturer`) attend l'identifiant
 * numérique en chemin (whereNumber). Le format de la référence est fixé et documenté dans
 * api/app/Domain/Transferts/Models/Transfert.php::booted() : `'TRF-'.str_pad($id, 6, '0', STR_PAD_LEFT)`,
 * donc l'identifiant se retrouve exactement en retirant ce préfixe (voir `idDepuisReference`
 * dans api.ts) — aucun champ n'est deviné, seulement dérivé d'un format déjà fixé côté serveur.
 */
export interface TransfertChauffeur {
  reference: string
  sejour_reference: string | null
  lieu_de_prise_en_charge: string
  commune: string | null
  date_heure_prevue: string
  nombre_passagers: number
  nombre_bagages: number
  etat: string
  etat_libelle: string
  vehicule: string | null
  montant_verse_au_chauffeur: number | null
}

/** GET /chauffeur/gains (GestionDesTransferts::mesGains). */
export interface GainsChauffeur {
  total_gagne: number
  solde_du: number
}

/** api/app/Http/Resources/Transferts/VehiculeResource.php. */
export interface VehiculeChauffeur {
  id: number
  chauffeur_id: number
  type_vehicule_id: number
  type_vehicule: string | null
  immatriculation: string
  actif: boolean
}

/** POST /chauffeur/vehicules. */
export interface SaisieVehiculeChauffeur {
  type_vehicule_id: number
  immatriculation: string
}
