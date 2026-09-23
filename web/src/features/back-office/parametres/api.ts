import { envoyer, lire } from '../../../shared/api/client'
import type {
  AbonneNewsletter,
  Article,
  Banniere,
  Diapositive,
  DonneesParametres,
  Onglet,
  PageDe,
  SaisieArticle,
  SaisieBanniere,
  SaisieDiapositive,
} from './types'

export const listerLesParametres = (): Promise<DonneesParametres> => lire<DonneesParametres>('/backoffice/parametres')

export const enregistrerUnOnglet = (onglet: string, valeurs: Record<string, unknown>): Promise<{ onglets: Onglet[] }> =>
  envoyer<{ onglets: Onglet[] }>(`/backoffice/parametres/${onglet}`, { valeurs }, 'put')

export const listerLesArticles = (filtres: Record<string, unknown> = {}): Promise<PageDe<Article>> =>
  lire<PageDe<Article>>('/backoffice/articles', filtres)

export const creerUnArticle = (saisie: SaisieArticle): Promise<Article> => envoyer<Article>('/backoffice/articles', saisie)

export const modifierUnArticle = (id: number, saisie: SaisieArticle): Promise<Article> =>
  envoyer<Article>(`/backoffice/articles/${id}`, saisie, 'put')

export const listerLesBannieres = (): Promise<Banniere[]> => lire<Banniere[]>('/backoffice/bannieres')

export const creerUneBanniere = (saisie: SaisieBanniere): Promise<Banniere> => envoyer<Banniere>('/backoffice/bannieres', saisie)

export const modifierUneBanniere = (id: number, saisie: SaisieBanniere): Promise<Banniere> =>
  envoyer<Banniere>(`/backoffice/bannieres/${id}`, saisie, 'put')

export const supprimerUneBanniere = (id: number): Promise<null> => envoyer<null>(`/backoffice/bannieres/${id}`, undefined, 'delete')

export const listerLeCarrousel = (): Promise<Diapositive[]> => lire<Diapositive[]>('/backoffice/carrousel')

export const creerUneDiapositive = (saisie: SaisieDiapositive): Promise<Diapositive> =>
  envoyer<Diapositive>('/backoffice/carrousel', saisie)

export const modifierUneDiapositive = (id: number, saisie: SaisieDiapositive): Promise<Diapositive> =>
  envoyer<Diapositive>(`/backoffice/carrousel/${id}`, saisie, 'put')

export const supprimerUneDiapositive = (id: number): Promise<null> => envoyer<null>(`/backoffice/carrousel/${id}`, undefined, 'delete')

export const listerLesAbonnesNewsletter = (filtres: Record<string, unknown> = {}): Promise<PageDe<AbonneNewsletter>> =>
  lire<PageDe<AbonneNewsletter>>('/backoffice/newsletter', filtres)

export const desabonnerDeLaNewsletter = (id: number): Promise<AbonneNewsletter> =>
  envoyer<AbonneNewsletter>(`/backoffice/newsletter/${id}/desabonner`, undefined, 'put')
