import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useSession } from '../../features/auth/session'
import type { Utilisateur } from '../../features/auth/types'
import { RoutesApplication } from '../../routes/RoutesApplication'
import i18n from '../i18n'

// jsdom n'a pas matchMedia, dont Ant Design a besoin.
Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

class ResizeObserverSimule {
  observe() {}
  unobserve() {}
  disconnect() {}
}
vi.stubGlobal('ResizeObserver', ResizeObserverSimule)

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
  // Un client par appel : useQuery (page d'accueil) exige un QueryClientProvider,
  // et un client neuf évite qu'un résultat mis en cache fuite d'un test à l'autre.
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[adresse]}>
        <RoutesApplication />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(async () => {
  useSession.setState({ jeton: null, utilisateur: null, statut: 'anonyme' })
  localStorage.clear()
  await i18n.changeLanguage('fr')
  document.documentElement.lang = 'fr'
})

describe('menu du back office, filtré par profil (P1-WEB-03)', () => {
  it('ne montre au gestionnaire que l’exploitation quotidienne', async () => {
    useSession.setState({
      jeton: 'j',
      utilisateur: compte({ profil: 'gestionnaire', espace: 'backoffice' }),
      statut: 'connecte',
    })
    monter('/admin')

    expect(await screen.findByText('Réservations')).toBeInTheDocument()
    expect(screen.getByText('Caisse')).toBeInTheDocument()
    expect(screen.queryByText('Paramètres')).not.toBeInTheDocument()
    expect(screen.queryByText('Personnel et agences')).not.toBeInTheDocument()
  })

  it('montre à l’administrateur les écrans réservés en plus', async () => {
    useSession.setState({
      jeton: 'j',
      utilisateur: compte({ profil: 'administrateur', espace: 'backoffice' }),
      statut: 'connecte',
    })
    monter('/admin')

    expect(await screen.findByText('Paramètres')).toBeInTheDocument()
    expect(screen.getByText('Personnel et agences')).toBeInTheDocument()
    expect(screen.getByText('Factures normalisées')).toBeInTheDocument()
  })

  it('n’ajoute rien au menu d’un espace qui n’est pas le back office', async () => {
    useSession.setState({ jeton: 'j', utilisateur: compte({ profil: 'client', espace: 'client' }), statut: 'connecte' })
    monter('/mon-espace')

    expect(await screen.findByText('Tableau de bord')).toBeInTheDocument()
    expect(screen.queryByText('Caisse')).not.toBeInTheDocument()
  })

  it('refuse au gestionnaire l’accès direct à un écran réservé aux administrateurs', async () => {
    useSession.setState({
      jeton: 'j',
      utilisateur: compte({ profil: 'gestionnaire', espace: 'backoffice' }),
      statut: 'connecte',
    })
    monter('/admin/parametres')

    expect(await screen.findByText('403')).toBeInTheDocument()
  })

  it('laisse l’administrateur entrer sur un écran réservé', async () => {
    useSession.setState({
      jeton: 'j',
      utilisateur: compte({ profil: 'administrateur', espace: 'backoffice' }),
      statut: 'connecte',
    })
    monter('/admin/parametres')

    // « Paramètres » apparaît deux fois : le titre du VRAI écran (P1-BO-10, pas un
    // placeholder « bientôt disponible ») ET l'entrée de menu active.
    expect(await screen.findAllByText('Paramètres')).toHaveLength(2)
    expect(screen.queryByText('403')).not.toBeInTheDocument()
  })
})

describe('bascule de langue fr / en (P1-WEB-05)', () => {
  it('traduit le site public au clic, sans recharger la page', async () => {
    monter('/')
    expect(await screen.findByRole('heading', { name: 'Votre résidence meublée à Abidjan' })).toBeInTheDocument()

    await userEvent.click(screen.getByText('EN'))

    expect(await screen.findByRole('heading', { name: 'Your furnished residence in Abidjan' })).toBeInTheDocument()
    expect(document.documentElement.lang).toBe('en')
  })

  it('est proposée dans l’espace client', async () => {
    useSession.setState({ jeton: 'j', utilisateur: compte({ profil: 'client', espace: 'client' }), statut: 'connecte' })
    monter('/mon-espace')

    expect(await screen.findByText('FR')).toBeInTheDocument()
    expect(screen.getByText('EN')).toBeInTheDocument()
  })

  it('ne s’affiche pas dans le back office (fr uniquement, CdC § 13.2)', async () => {
    useSession.setState({
      jeton: 'j',
      utilisateur: compte({ profil: 'administrateur', espace: 'backoffice' }),
      statut: 'connecte',
    })
    monter('/admin')

    await screen.findByText('Tableau de bord')
    expect(screen.queryByText('EN')).not.toBeInTheDocument()
  })
})
