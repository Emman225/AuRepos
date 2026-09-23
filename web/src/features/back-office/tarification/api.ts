import { envoyer, lire } from '../../../shared/api/client'
import type {
  Anomalie,
  BaremeLivraisonRepas,
  BaremeTransfert,
  ChangementAValider,
  CodePromo,
  Derogation,
  Grille,
  LigneReferentiel,
  LigneSaisie,
  PageDe,
  PrixNegocie,
  RapportDeVerification,
  SaisieBaremeLivraisonRepas,
  SaisieBaremeTransfert,
  SaisieCodePromo,
  SaisiePrixNegocie,
  Simulation,
} from './types'

export type Cible = { type_logement_id: number } | { logement_id: number }

export const afficherLaGrille = (cible: Cible): Promise<Grille> => lire<Grille>('/backoffice/tarification/grille', cible)

export const enregistrerLaGrille = (cible: Cible, lignes: LigneSaisie[]): Promise<{ anomalies: Anomalie[] }> =>
  envoyer<{ anomalies: Anomalie[] }>('/backoffice/tarification/grille', { ...cible, lignes }, 'put')

export const verifierLaGrille = (): Promise<RapportDeVerification> => lire<RapportDeVerification>('/backoffice/tarification/verification')

export const simulerUnSejour = (logementId: number, arrivee: string, depart: string): Promise<Simulation> =>
  lire<Simulation>('/backoffice/tarification/simulation', { logement_id: logementId, arrivee, depart })

export const proposerLePourcentageEntrepriseGlobal = (taux: number, motif?: string): Promise<ChangementAValider> =>
  envoyer<ChangementAValider>('/backoffice/tarification/pourcentage-entreprise', { taux, motif })

export const listerLesDerogations = (): Promise<{ derogations: Derogation[] }> => lire<{ derogations: Derogation[] }>('/backoffice/tarification/derogations')

export const listerLesChangements = (): Promise<PageDe<ChangementAValider>> => lire<PageDe<ChangementAValider>>('/backoffice/changements')

export const deciderUnChangement = (id: number, decision: 'valider' | 'refuser', motif?: string): Promise<ChangementAValider> =>
  envoyer<ChangementAValider>(`/backoffice/changements/${id}/decision`, { decision, motif }, 'put')

export const listerLesPrixNegocies = (filtres: Record<string, unknown> = {}): Promise<PageDe<PrixNegocie>> =>
  lire<PageDe<PrixNegocie>>('/backoffice/prix-negocies', filtres)

export const enregistrerUnPrixNegocie = (saisie: SaisiePrixNegocie): Promise<PrixNegocie> =>
  envoyer<PrixNegocie>('/backoffice/prix-negocies', saisie)

export const desactiverUnPrixNegocie = (id: number): Promise<PrixNegocie> =>
  envoyer<PrixNegocie>(`/backoffice/prix-negocies/${id}/desactivation`, undefined, 'put')

export const listerLesCodesPromo = (filtres: Record<string, unknown> = {}): Promise<PageDe<CodePromo>> =>
  lire<PageDe<CodePromo>>('/backoffice/codes-promo', filtres)

export const creerUnCodePromo = (saisie: SaisieCodePromo): Promise<CodePromo> => envoyer<CodePromo>('/backoffice/codes-promo', saisie)

export const modifierUnCodePromo = (id: number, saisie: { actif?: boolean; date_fin?: string; description?: string }): Promise<CodePromo> =>
  envoyer<CodePromo>(`/backoffice/codes-promo/${id}`, saisie, 'put')

export const listerUnReferentiel = (slug: string, filtres: Record<string, unknown> = {}): Promise<PageDe<LigneReferentiel>> =>
  lire<PageDe<LigneReferentiel>>(`/backoffice/referentiels/${slug}`, filtres)

export const creerUneLigneDeReferentiel = (slug: string, champs: Record<string, unknown>): Promise<LigneReferentiel> =>
  envoyer<LigneReferentiel>(`/backoffice/referentiels/${slug}`, champs)

export const modifierUneLigneDeReferentiel = (slug: string, id: number, champs: Record<string, unknown>): Promise<LigneReferentiel> =>
  envoyer<LigneReferentiel>(`/backoffice/referentiels/${slug}/${id}`, champs, 'put')

export const supprimerUneLigneDeReferentiel = (slug: string, id: number): Promise<void> =>
  envoyer<void>(`/backoffice/referentiels/${slug}/${id}`, undefined, 'delete')

export const listerLesBaremesLivraisonRepas = (): Promise<BaremeLivraisonRepas[]> =>
  lire<BaremeLivraisonRepas[]>('/backoffice/baremes-livraison-repas')

export const creerUnBaremeLivraisonRepas = (saisie: SaisieBaremeLivraisonRepas): Promise<BaremeLivraisonRepas> =>
  envoyer<BaremeLivraisonRepas>('/backoffice/baremes-livraison-repas', saisie)

export const modifierUnBaremeLivraisonRepas = (id: number, saisie: Partial<SaisieBaremeLivraisonRepas>): Promise<BaremeLivraisonRepas> =>
  envoyer<BaremeLivraisonRepas>(`/backoffice/baremes-livraison-repas/${id}`, saisie, 'put')

export const listerLesBaremesTransfert = (): Promise<BaremeTransfert[]> => lire<BaremeTransfert[]>('/backoffice/baremes-transfert')

export const creerUnBaremeTransfert = (saisie: SaisieBaremeTransfert): Promise<BaremeTransfert> =>
  envoyer<BaremeTransfert>('/backoffice/baremes-transfert', saisie)

export const modifierUnBaremeTransfert = (id: number, saisie: { prix: number }): Promise<BaremeTransfert> =>
  envoyer<BaremeTransfert>(`/backoffice/baremes-transfert/${id}`, saisie, 'put')

export const supprimerUnBaremeTransfert = (id: number): Promise<void> =>
  envoyer<void>(`/backoffice/baremes-transfert/${id}`, undefined, 'delete')
