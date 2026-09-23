import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import '../../shared/i18n'
import { ErreurApi } from '../../shared/api/client'
import * as apiAuth from './api'
import { accueilDe } from './espaces'
import { PageConnexion } from './PageConnexion'
import { RequireProfil } from './RequireProfil'
import { useSession } from './session'
import type { Utilisateur } from './types'

// jsdom n'a pas matchMedia, dont Ant Design a besoin.
Object.defineProperty(window, 'matchMedia', {
  value: () => ({ matches: false, addListener() {}, removeListener() {}, addEventListener() {}, removeEventListener() {} }),
})

const compte = (surcharge: Partial<Utilisateur> = {}): Utilisateur => ({
  id: 1,
  nom: 'Koné',
  prenoms: 'Awa',
  nom_complet: 'Awa Koné',
  email: 'awa@exemple.ci',
  telephone: null,
  profil: 'client',
  profil_libelle: 'Client',
  espace: 'client',
  agence: null,
  peut_encaisser: false,
  ...surcharge,
})

function monter(adresse: string) {
  return render(
    <MemoryRouter initialEntries={[adresse]}>
      <Routes>
        <Route path="/connexion" element={<PageConnexion />} />
        <Route path="/acces-refuse" element={<p>accès refusé</p>} />
        <Route path="/mon-espace" element={<p>espace client</p>} />
        <Route
          path="/admin/*"
          element={
            <RequireProfil espace="backoffice">
              <p>back office</p>
            </RequireProfil>
          }
        />
        <Route
          path="/admin-seulement"
          element={
            <RequireProfil espace="backoffice" profils={['administrateur', 'super_administrateur']}>
              <p>réservé aux administrateurs</p>
            </RequireProfil>
          }
        />
      </Routes>
    </MemoryRouter>,
  )
}

beforeEach(() => {
  vi.restoreAllMocks()
  useSession.setState({ jeton: null, utilisateur: null, statut: 'anonyme' })
})

describe('garde de routes', () => {
  it('renvoie un visiteur non connecté vers la page de connexion', () => {
    monter('/admin')
    expect(screen.getByRole('heading', { name: 'Connexion' })).toBeInTheDocument()
    expect(screen.queryByText('back office')).not.toBeInTheDocument()
  })

  it('refuse un compte dont l’espace n’est pas le bon', () => {
    useSession.setState({ jeton: 'j', utilisateur: compte(), statut: 'connecte' })
    monter('/admin')
    expect(screen.getByText('accès refusé')).toBeInTheDocument()
  })

  it('laisse entrer le bon espace', () => {
    useSession.setState({ jeton: 'j', utilisateur: compte({ profil: 'gestionnaire', espace: 'backoffice' }), statut: 'connecte' })
    monter('/admin')
    expect(screen.getByText('back office')).toBeInTheDocument()
  })

  it('réserve certains écrans du back office aux administrateurs', () => {
    useSession.setState({ jeton: 'j', utilisateur: compte({ profil: 'gestionnaire', espace: 'backoffice' }), statut: 'connecte' })
    monter('/admin-seulement')
    expect(screen.getByText('accès refusé')).toBeInTheDocument()
  })

  it('n’affiche rien de l’espace tant que le serveur n’a pas confirmé le compte', () => {
    useSession.setState({ jeton: 'j', utilisateur: null, statut: 'inconnu' })
    monter('/admin')
    expect(screen.queryByText('back office')).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Connexion' })).not.toBeInTheDocument()
  })
})

describe('page de connexion', () => {
  it('ouvre la session et envoie le client dans son espace', async () => {
    vi.spyOn(apiAuth, 'connexion').mockResolvedValue({ jeton: 'jeton-1', type: 'Bearer', expire_dans: 86400, utilisateur: compte() })
    monter('/connexion')

    await userEvent.type(screen.getByLabelText(/Identifiant/), 'awa@exemple.ci')
    await userEvent.type(screen.getByLabelText(/Mot de passe/), 'secret')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByText('espace client')).toBeInTheDocument()
    expect(apiAuth.connexion).toHaveBeenCalledWith('awa@exemple.ci', 'secret')
    expect(useSession.getState().jeton).toBe('jeton-1')
  })

  it('affiche le message du serveur quand la connexion est refusée', async () => {
    vi.spyOn(apiAuth, 'connexion').mockRejectedValue(new ErreurApi('Identifiant ou mot de passe incorrect.', 401))
    monter('/connexion')

    await userEvent.type(screen.getByLabelText(/Identifiant/), 'awa@exemple.ci')
    await userEvent.type(screen.getByLabelText(/Mot de passe/), 'faux')
    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('Identifiant ou mot de passe incorrect.')
    expect(useSession.getState().statut).toBe('anonyme')
  })

  it('n’appelle pas le serveur si un champ est vide', async () => {
    const appel = vi.spyOn(apiAuth, 'connexion')
    monter('/connexion')

    await userEvent.click(screen.getByRole('button', { name: 'Se connecter' }))

    await waitFor(() => expect(screen.getByText('Saisissez votre identifiant.')).toBeInTheDocument())
    expect(appel).not.toHaveBeenCalled()
  })

  it('renvoie directement dans son espace un compte déjà connecté', () => {
    useSession.setState({ jeton: 'j', utilisateur: compte(), statut: 'connecte' })
    monter('/connexion')
    expect(screen.getByText('espace client')).toBeInTheDocument()
  })
})

describe('redirection par profil', () => {
  it('donne une adresse d’accueil à chacun des neuf espaces', () => {
    const espaces = ['backoffice', 'assistance', 'proprietaire', 'agent', 'chauffeur', 'livreur', 'restaurateur', 'apporteur', 'client'] as const
    const adresses = espaces.map(accueilDe)

    expect(new Set(adresses).size).toBe(9)
    adresses.forEach((a) => expect(a).toMatch(/^\/[a-z-]+$/))
  })
})
