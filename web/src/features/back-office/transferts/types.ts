export interface PageDe<T> {
  elements: T[]
  pagination: { page: number; par_page: number; total: number; derniere_page: number }
}

/** api/app/Http/Resources/Backoffice/TransfertResource.php. Le code de prise en charge n'apparaît jamais ici (CdC § 11). */
export interface TransfertBackOffice {
  id: number
  reference: string
  sejour_reference: string | null
  lieu_de_prise_en_charge: string
  commune: string | null
  type_vehicule_souhaite: string | null
  date_heure_prevue: string
  nombre_passagers: number
  nombre_bagages: number
  montant: number
  etat: string
  etat_libelle: string
  chauffeur: string | null
  vehicule: string | null
  montant_verse_au_chauffeur: number | null
  notes: string | null
  code_prise_en_charge_emis: boolean
  cree_le: string | null
}

/** POST /backoffice/transferts/{transfert}/affecter — montant_verse_au_chauffeur est une SAISIE manuelle, jamais calculée côté écran. */
export interface SaisieAffectation {
  chauffeur_id: number
  vehicule_id: number
  montant_verse_au_chauffeur: number
}
