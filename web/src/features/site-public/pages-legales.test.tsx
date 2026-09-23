import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'

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

function monter(adresse: string) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={[adresse]}>
        <RoutesApplication />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  useSession.setState({ jeton: null, utilisateur: null, statut: 'anonyme' })
  vi.restoreAllMocks()
})

describe('pages légales (P1-PUB-08)', () => {
  it('affiche le texte des CGV saisi dans les Paramètres', async () => {
    vi.spyOn(clientApi, 'lire').mockResolvedValue({
      'conditions.cgv': 'Article 1 : ...',
      'conditions.confidentialite': null,
    })
    monter('/conditions-generales')

    expect(await screen.findByText('Article 1 : ...')).toBeInTheDocument()
  })

  it('affiche un message d’attente tant qu’aucun texte n’a été saisi', async () => {
    vi.spyOn(clientApi, 'lire').mockResolvedValue({ 'conditions.cgv': null, 'conditions.confidentialite': null })
    monter('/confidentialite')

    expect(await screen.findByText('Ce contenu n’est pas encore disponible.')).toBeInTheDocument()
  })

  it('propose les deux pages légales depuis le pied de page du site public', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/referentiels/communes') return [] as never
      if (url === '/referentiels/types-logement') return [] as never
      if (url === '/accueil') return { mises_en_avant: [], bannieres: [], temoignages: [], carrousel: [] } as never
      return { 'conditions.cgv': null, 'conditions.confidentialite': null } as never
    })
    monter('/')

    expect(await screen.findByRole('link', { name: 'Conditions générales de vente' })).toHaveAttribute(
      'href',
      '/conditions-generales',
    )
    expect(screen.getByRole('link', { name: 'Politique de confidentialité' })).toHaveAttribute(
      'href',
      '/confidentialite',
    )
  })
})
