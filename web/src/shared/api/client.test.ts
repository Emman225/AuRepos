import type { AxiosAdapter, AxiosResponse, InternalAxiosRequestConfig } from 'axios'
import { AxiosError } from 'axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { brancherSession, creerClient, ErreurApi } from './client'

/** Faux serveur : répond ce qu'on lui dit, sans réseau. */
function adaptateur(statut: number, corps: unknown, capture?: (c: InternalAxiosRequestConfig) => void): AxiosAdapter {
  return async (config) => {
    capture?.(config)
    const reponse = { data: corps, status: statut, statusText: '', headers: {}, config } as AxiosResponse
    if (statut >= 400) throw new AxiosError('échec', String(statut), config, null, reponse)
    return reponse
  }
}

describe('client API', () => {
  beforeEach(() => brancherSession(() => null, () => {}))

  it('joint le jeton en en-tête Authorization, jamais dans le corps', async () => {
    brancherSession(() => 'jeton-abc', () => {})
    let envoye: InternalAxiosRequestConfig | undefined
    const client = creerClient('http://api.test')
    client.defaults.adapter = adaptateur(200, { success: true, message: '', data: 1, errors: null }, (c) => (envoye = c))

    await client.post('/sejours', { logement: 4 })

    expect(envoye?.headers.Authorization).toBe('Bearer jeton-abc')
    expect(String(envoye?.data)).not.toContain('jeton-abc')
  })

  it('transforme une erreur de validation en ErreurApi avec le détail par champ', async () => {
    const client = creerClient('http://api.test')
    client.defaults.adapter = adaptateur(422, {
      success: false,
      message: 'Certaines informations sont incorrectes.',
      data: null,
      errors: { nom: ['Le nom est obligatoire.'] },
    })

    const erreur = await client.post('/x').catch((e: unknown) => e)

    expect(erreur).toBeInstanceOf(ErreurApi)
    expect((erreur as ErreurApi).statut).toBe(422)
    expect((erreur as ErreurApi).champs).toEqual({ nom: ['Le nom est obligatoire.'] })
    expect((erreur as ErreurApi).message).toBe('Certaines informations sont incorrectes.')
  })

  it('signale la session expirée sur un 401', async () => {
    const expiree = vi.fn()
    brancherSession(() => 'vieux-jeton', expiree)
    const client = creerClient('http://api.test')
    client.defaults.adapter = adaptateur(401, { success: false, message: 'Vous devez vous connecter.', data: null, errors: null })

    await client.get('/moi').catch(() => {})

    expect(expiree).toHaveBeenCalledOnce()
  })

  it('prolonge la session une seule fois puis rejoue l’appel avec le nouveau jeton', async () => {
    let jeton = 'expire'
    const rafraichir = vi.fn(async () => (jeton = 'neuf'))
    const expiree = vi.fn()
    brancherSession(() => jeton, expiree, rafraichir)

    const vus: string[] = []
    const client = creerClient('http://api.test')
    client.defaults.adapter = async (config) => {
      vus.push(String(config.headers.Authorization))
      const ok = config.headers.Authorization === 'Bearer neuf'
      const reponse = {
        data: ok ? { success: true, message: '', data: 'contenu', errors: null } : { success: false, message: 'expiré', data: null, errors: null },
        status: ok ? 200 : 401,
        statusText: '',
        headers: {},
        config,
      } as AxiosResponse
      if (!ok) throw new AxiosError('échec', '401', config, null, reponse)
      return reponse
    }

    // Trois appels simultanés reçoivent un 401 : un seul rafraîchissement doit partir.
    const reponses = await Promise.all([client.get('/a'), client.get('/b'), client.get('/c')])

    expect(reponses.map((r) => r.data.data)).toEqual(['contenu', 'contenu', 'contenu'])
    expect(rafraichir).toHaveBeenCalledOnce()
    expect(expiree).not.toHaveBeenCalled()
    expect(vus.filter((v) => v === 'Bearer neuf')).toHaveLength(3)
  })

  it('ferme la session quand la prolongation échoue', async () => {
    const expiree = vi.fn()
    brancherSession(() => 'expire', expiree, async () => null)
    const client = creerClient('http://api.test')
    client.defaults.adapter = adaptateur(401, { success: false, message: 'Vous devez vous connecter.', data: null, errors: null })

    await client.get('/moi').catch(() => {})

    expect(expiree).toHaveBeenCalledOnce()
  })

  it('ne prend pas une connexion refusée pour une session expirée', async () => {
    const expiree = vi.fn()
    brancherSession(() => null, expiree)
    const client = creerClient('http://api.test')
    client.defaults.adapter = adaptateur(401, { success: false, message: 'Identifiant ou mot de passe incorrect.', data: null, errors: null })

    const erreur = (await client.post('/auth/connexion', {}).catch((e: unknown) => e)) as ErreurApi

    expect(erreur.message).toBe('Identifiant ou mot de passe incorrect.')
    expect(expiree).not.toHaveBeenCalled()
  })

  it('donne un message clair quand le serveur ne répond pas', async () => {
    const client = creerClient('http://api.test')
    client.defaults.adapter = async (config) => {
      throw new AxiosError('Network Error', 'ERR_NETWORK', config)
    }

    const erreur = (await client.get('/etat').catch((e: unknown) => e)) as ErreurApi

    expect(erreur.statut).toBe(0)
    expect(erreur.message).toContain('Le serveur ne répond pas')
  })
})
