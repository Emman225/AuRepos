import { z } from 'zod'
import type { OccupantSaisi, SaisieEstimation, TypeDePiece } from './types'

export const TYPES_DE_PIECE: TypeDePiece[] = ['cni', 'passeport', 'permis', 'carte_consulaire', 'autre']

/**
 * Ce que le formulaire du tunnel tient. Aucune règle de prix, de taxe ni de
 * disponibilité ici : seulement de quoi composer une intention valide. Les refus
 * métier (durée minimale, plafond de crédit, code promo expiré) viennent du serveur.
 */
export const schemaReservation = z.object({
  arrivee: z.string().min(1, 'reservation.erreurs.datesObligatoires'),
  depart: z.string().min(1, 'reservation.erreurs.datesObligatoires'),
  adultes: z.number().int().min(1, 'reservation.erreurs.adultesObligatoires').max(60),
  enfants: z.number().int().min(0).max(60),
  arrivee_tardive: z.boolean(),
  depart_tardif: z.boolean(),
  heure_arrivee_prevue: z.string(),
  code_promo: z.string(),
  utiliser_mes_points: z.boolean(),
  mode_reglement: z.enum(['en_ligne', 'agence', 'a_terme']),
  bon_de_commande: z.string(),
  occupants: z.array(
    z.object({
      nom: z.string().trim().min(1, 'reservation.erreurs.nomOccupantObligatoire'),
      prenoms: z.string(),
      enfant: z.boolean(),
      type_piece: z.enum(['cni', 'passeport', 'permis', 'carte_consulaire', 'autre']).nullable(),
      numero_piece: z.string(),
      telephone: z.string(),
    }),
  ),
})

export type SaisieFormulaire = z.infer<typeof schemaReservation>

export const OCCUPANT_VIDE: SaisieFormulaire['occupants'][number] = {
  nom: '',
  prenoms: '',
  enfant: false,
  type_piece: null,
  numero_piece: '',
  telephone: '',
}

const vide = (texte: string): string | undefined => (texte.trim() === '' ? undefined : texte.trim())

/** Ce qu'on envoie à l'estimation : des dates, des occupants, des options. Jamais un prix. */
export function versEstimation(valeurs: SaisieFormulaire): SaisieEstimation {
  return {
    arrivee: valeurs.arrivee,
    depart: valeurs.depart,
    adultes: valeurs.adultes,
    enfants: valeurs.enfants,
    arrivee_tardive: valeurs.arrivee_tardive,
    depart_tardif: valeurs.depart_tardif,
    code_promo: vide(valeurs.code_promo),
  }
}

/** Les occupants nommés sont facultatifs : une liste laissée vide n'est pas envoyée. */
export function versOccupants(valeurs: SaisieFormulaire): OccupantSaisi[] | undefined {
  const decrits = valeurs.occupants.filter((occupant) => occupant.nom.trim() !== '')
  if (decrits.length === 0) return undefined

  return decrits.map((occupant) => ({
    nom: occupant.nom.trim(),
    prenoms: vide(occupant.prenoms) ?? null,
    enfant: occupant.enfant,
    type_piece: occupant.type_piece,
    numero_piece: vide(occupant.numero_piece) ?? null,
    telephone: vide(occupant.telephone) ?? null,
  }))
}

export const heureOuRien = (heure: string): string | undefined => vide(heure)
export const texteOuRien = vide

/** Réglages publics dont le tunnel a besoin (GET /configuration). */
export interface ConfigurationPaiement {
  'comptant.paiement_en_ligne_actif': boolean
  'general.plafond_paiement_en_ligne': number
}
