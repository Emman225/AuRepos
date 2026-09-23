import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'

/** Forme unique de toutes les réponses de l'API (voir api/app/Support/Api/ReponseApi.php). */
export interface Enveloppe<T> {
  success: boolean
  message: string
  data: T
  errors: Record<string, string[]> | null
}

/**
 * Erreur métier lisible par l'interface : le message vient du serveur, en
 * français clair, et `champs` porte le détail de validation (HTTP 422).
 */
export class ErreurApi extends Error {
  readonly statut: number
  readonly champs: Record<string, string[]> | null

  constructor(message: string, statut: number, champs: Record<string, string[]> | null = null) {
    super(message)
    this.name = 'ErreurApi'
    this.statut = statut
    this.champs = champs
  }
}

const MESSAGE_RESEAU = 'Le serveur ne répond pas. Vérifiez votre connexion puis réessayez.'

type LecteurDeJeton = () => string | null
type SurSessionExpiree = () => void
/** Demande un nouveau jeton au serveur ; rend null si la session ne peut plus être prolongée. */
type Rafraichisseur = () => Promise<string | null>

let lireJeton: LecteurDeJeton = () => null
let surSessionExpiree: SurSessionExpiree = () => {}
let rafraichir: Rafraichisseur | null = null

/** Branché par le module d'authentification : le client ne connaît pas le magasin de session. */
export function brancherSession(
  lecteur: LecteurDeJeton,
  expiree: SurSessionExpiree,
  rafraichisseur?: Rafraichisseur,
): void {
  lireJeton = lecteur
  surSessionExpiree = expiree
  rafraichir = rafraichisseur ?? null
}

// Dix appels qui reçoivent un 401 en même temps ne déclenchent qu'UN rafraîchissement :
// le serveur révoque l'ancien jeton dès le premier, les neuf autres échoueraient.
let rafraichissementEnCours: Promise<string | null> | null = null

function rafraichirUneSeuleFois(): Promise<string | null> {
  if (!rafraichir) return Promise.resolve(null)
  rafraichissementEnCours ??= rafraichir()
    .catch(() => null)
    .finally(() => {
      rafraichissementEnCours = null
    })
  return rafraichissementEnCours
}

const SANS_REPRISE = ['/auth/connexion', '/auth/rafraichir', '/auth/deconnexion']

/**
 * La règle Laravel `boolean` n'accepte que 0/1/"0"/"1" (pas les chaînes "true"/"false"
 * que produirait le sérialiseur par défaut d'axios pour un paramètre de requête booléen).
 */
function serialiserLesParametres(params: Record<string, unknown>): string {
  const recherche = new URLSearchParams()
  for (const [cle, valeur] of Object.entries(params)) {
    if (valeur === undefined || valeur === null) continue
    if (Array.isArray(valeur)) {
      for (const element of valeur) recherche.append(`${cle}[]`, String(element))
      continue
    }
    recherche.append(cle, typeof valeur === 'boolean' ? (valeur ? '1' : '0') : String(valeur))
  }
  return recherche.toString()
}

export function creerClient(baseURL: string): AxiosInstance {
  const instance = axios.create({
    baseURL,
    timeout: 30_000,
    headers: { Accept: 'application/json' },
    paramsSerializer: serialiserLesParametres,
  })

  instance.interceptors.request.use((config) => {
    const jeton = lireJeton()
    if (jeton) config.headers.Authorization = `Bearer ${jeton}`
    return config
  })

  instance.interceptors.response.use(
    (reponse) => reponse,
    async (erreur: AxiosError<Enveloppe<unknown>>) => {
      if (!erreur.response) throw new ErreurApi(MESSAGE_RESEAU, 0)

      const { status, data } = erreur.response
      const config = erreur.config as (InternalAxiosRequestConfig & { _rejouee?: boolean }) | undefined

      if (status === 401) {
        const reprise = config && !config._rejouee && lireJeton() && !SANS_REPRISE.some((u) => config.url?.endsWith(u))
        if (reprise) {
          const jeton = await rafraichirUneSeuleFois()
          if (jeton) {
            config._rejouee = true
            config.headers.Authorization = `Bearer ${jeton}`
            return instance.request(config)
          }
        }
        // Une connexion refusée n'est pas une session expirée.
        if (!config?.url?.endsWith('/auth/connexion')) surSessionExpiree()
      }

      throw new ErreurApi(data?.message ?? 'La demande ne peut pas aboutir.', status, data?.errors ?? null)
    },
  )

  return instance
}

export const api = creerClient(import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1')

/** Lit une ressource et rend directement `data`, l'enveloppe étant dépliée. */
export async function lire<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  const { data } = await api.get<Enveloppe<T>>(url, { params })
  return data.data
}

/** Envoie une intention au serveur. Aucun prix ni aucune taxe ne se calcule côté navigateur. */
export async function envoyer<T>(
  url: string,
  corps?: unknown,
  methode: 'post' | 'put' | 'patch' | 'delete' = 'post',
): Promise<T> {
  const { data } = await api.request<Enveloppe<T>>({ url, method: methode, data: corps })
  return data.data
}

export type FormatExport = 'xlsx' | 'docx' | 'pdf'

/**
 * Télécharge un export de liste (CdC § 6.8) : la réponse est le fichier brut, pas l'enveloppe
 * habituelle, donc en dehors de `lire()`. Le jeton part comme pour tout appel (intercepteur),
 * une simple navigation `<a href>` ne le pourrait pas.
 */
export async function telechargerExport(
  url: string,
  filtres: Record<string, unknown>,
  format: FormatExport,
  nomFichier: string,
): Promise<void> {
  const reponse = await api.get<Blob>(url, { params: { ...filtres, format }, responseType: 'blob' })
  const urlObjet = URL.createObjectURL(reponse.data)
  const lien = document.createElement('a')
  lien.href = urlObjet
  lien.download = `${nomFichier}.${format}`
  lien.click()
  URL.revokeObjectURL(urlObjet)
}
