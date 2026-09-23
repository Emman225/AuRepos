import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Article, ResultatsBlog } from './types'

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

const LISTE: ResultatsBlog = {
  elements: [
    { titre: 'Bien choisir son quartier à Abidjan', slug: 'bien-choisir-son-quartier', resume: 'Nos conseils pour choisir.', image: null, publie_le: '01/10/2026' },
  ],
  pagination: { page: 1, par_page: 9, total: 1, derniere_page: 1 },
}

const ARTICLE: Article = {
  titre: 'Bien choisir son quartier à Abidjan',
  slug: 'bien-choisir-son-quartier',
  resume: 'Nos conseils pour choisir.',
  contenu: 'Premier paragraphe.\n\nDeuxième paragraphe.',
  image: null,
  publie_le: '01/10/2026',
}

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

describe('blog public', () => {
  it('liste les articles publiés puis ouvre la fiche depuis le lien', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/blog') return LISTE as never
      if (url === '/blog/bien-choisir-son-quartier') return ARTICLE as never
      return { 'general.whatsapp': null } as never
    })
    monter('/blog')

    const lien = await screen.findByRole('link', { name: /Bien choisir son quartier à Abidjan/ })
    expect(lien).toHaveAttribute('href', '/blog/bien-choisir-son-quartier')
  })

  it('affiche la fiche d’un article avec son contenu en paragraphes', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/blog/bien-choisir-son-quartier') return ARTICLE as never
      return { 'general.whatsapp': null } as never
    })
    monter('/blog/bien-choisir-son-quartier')

    expect(await screen.findByRole('heading', { level: 1, name: 'Bien choisir son quartier à Abidjan' })).toBeInTheDocument()
    expect(screen.getByText('Premier paragraphe.')).toBeInTheDocument()
    expect(screen.getByText('Deuxième paragraphe.')).toBeInTheDocument()
  })

  it('affiche un message clair pour un article introuvable', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/blog/inconnu') throw new clientApi.ErreurApi('Introuvable', 404)
      return { 'general.whatsapp': null } as never
    })
    monter('/blog/inconnu')

    expect(await screen.findByText('Cet article n’existe pas ou n’est plus publié.')).toBeInTheDocument()
  })

  it('propose le lien Blog depuis l’en-tête du site public', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/referentiels/communes') return [] as never
      if (url === '/referentiels/types-logement') return [] as never
      if (url === '/accueil') return { mises_en_avant: [], bannieres: [], temoignages: [], carrousel: [] } as never
      return { 'general.whatsapp': null } as never
    })
    monter('/')

    expect(await screen.findByRole('link', { name: 'Blog' })).toHaveAttribute('href', '/blog')
  })
})
