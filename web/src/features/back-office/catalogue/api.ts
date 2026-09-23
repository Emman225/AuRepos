import { envoyer, lire } from '../../../shared/api/client'
import type {
  ActionDePublication,
  ChangementAValider,
  Galerie,
  LigneReferentiel,
  Logement,
  PageDe,
  Residence,
  SaisieLogement,
  SaisieResidence,
  SituationPrix,
  SituationPublication,
} from './types'

export const listerLesResidences = (filtres: Record<string, unknown> = {}): Promise<PageDe<Residence>> =>
  lire<PageDe<Residence>>('/backoffice/residences', filtres)

export const afficherLaResidence = (id: number): Promise<Residence> => lire<Residence>(`/backoffice/residences/${id}`)

export const creerUneResidence = (saisie: SaisieResidence): Promise<Residence> =>
  envoyer<Residence>('/backoffice/residences', saisie)

export const modifierLaResidence = (id: number, saisie: Partial<SaisieResidence>): Promise<Residence> =>
  envoyer<Residence>(`/backoffice/residences/${id}`, saisie, 'put')

export const listerLesLogements = (residenceId: number): Promise<Logement[]> =>
  lire<Logement[]>(`/backoffice/residences/${residenceId}/logements`)

export const afficherLeLogement = (residenceId: number, logementId: number): Promise<Logement> =>
  lire<Logement>(`/backoffice/residences/${residenceId}/logements/${logementId}`)

export const creerUnLogement = (residenceId: number, saisie: SaisieLogement): Promise<Logement> =>
  envoyer<Logement>(`/backoffice/residences/${residenceId}/logements`, saisie)

export const modifierLeLogement = (residenceId: number, logementId: number, saisie: Partial<SaisieLogement>): Promise<Logement> =>
  envoyer<Logement>(`/backoffice/residences/${residenceId}/logements/${logementId}`, saisie, 'put')

const baseLogement = (residenceId: number, logementId: number): string =>
  `/backoffice/residences/${residenceId}/logements/${logementId}`

export const afficherLaSituationDuPrix = (residenceId: number, logementId: number): Promise<SituationPrix> =>
  lire<SituationPrix>(`${baseLogement(residenceId, logementId)}/prix`)

export const proposerLePrixProprietaire = (
  residenceId: number,
  logementId: number,
  montant: number,
  nature: 'proposition' | 'accord',
  commentaire?: string,
): Promise<SituationPrix> =>
  envoyer<SituationPrix>(`${baseLogement(residenceId, logementId)}/prix/proprietaire`, { montant, nature, commentaire })

export const proposerLePrixDeVente = (
  residenceId: number,
  logementId: number,
  montant: number,
  motif?: string,
): Promise<ChangementAValider> =>
  envoyer<ChangementAValider>(`${baseLogement(residenceId, logementId)}/prix/vente`, { montant, motif })

/** Dérogation au pourcentage entreprise DE CE LOGEMENT (double validation) : `taux: null` la retire. */
export const proposerLaDerogationDePourcentage = (
  residenceId: number,
  logementId: number,
  taux: number | null,
  motif?: string,
): Promise<ChangementAValider> => envoyer<ChangementAValider>(`${baseLogement(residenceId, logementId)}/pourcentage-entreprise`, { taux, motif })

export const afficherLaPublication = (residenceId: number, logementId: number): Promise<SituationPublication> =>
  lire<SituationPublication>(`${baseLogement(residenceId, logementId)}/publication`)

export const agirSurLaPublication = (
  residenceId: number,
  logementId: number,
  action: ActionDePublication,
  motif?: string,
): Promise<Logement> => envoyer<Logement>(`${baseLogement(residenceId, logementId)}/publication`, { action, motif })

export const listerLesPhotos = (residenceId: number, logementId: number): Promise<Galerie> =>
  lire<Galerie>(`${baseLogement(residenceId, logementId)}/photos`)

export const ajouterUnePhoto = (residenceId: number, logementId: number, fichier: File, legende?: string): Promise<Galerie> => {
  const formulaire = new FormData()
  formulaire.append('photo', fichier)
  if (legende) formulaire.append('legende', legende)
  return envoyer<Galerie>(`${baseLogement(residenceId, logementId)}/photos`, formulaire)
}

export const definirLaCouverture = (residenceId: number, logementId: number, photoId: number): Promise<Galerie> =>
  envoyer<Galerie>(`${baseLogement(residenceId, logementId)}/photos/${photoId}`, { couverture: true }, 'put')

export const reordonnerLesPhotos = (residenceId: number, logementId: number, ordre: number[]): Promise<Galerie> =>
  envoyer<Galerie>(`${baseLogement(residenceId, logementId)}/photos/ordre`, { ordre }, 'put')

export const supprimerUnePhoto = (residenceId: number, logementId: number, photoId: number, motif: string): Promise<Galerie> =>
  envoyer<Galerie>(`${baseLogement(residenceId, logementId)}/photos/${photoId}`, { motif }, 'delete')

export const listerUnReferentiel = (slug: string, filtres: Record<string, unknown> = {}): Promise<PageDe<LigneReferentiel>> =>
  lire<PageDe<LigneReferentiel>>(`/backoffice/referentiels/${slug}`, filtres)

export const creerUneLigneDeReferentiel = (slug: string, champs: Record<string, unknown>): Promise<LigneReferentiel> =>
  envoyer<LigneReferentiel>(`/backoffice/referentiels/${slug}`, champs)

export const modifierUneLigneDeReferentiel = (slug: string, id: number, champs: Record<string, unknown>): Promise<LigneReferentiel> =>
  envoyer<LigneReferentiel>(`/backoffice/referentiels/${slug}/${id}`, champs, 'put')

export const supprimerUneLigneDeReferentiel = (slug: string, id: number): Promise<void> =>
  envoyer<void>(`/backoffice/referentiels/${slug}/${id}`, undefined, 'delete')
