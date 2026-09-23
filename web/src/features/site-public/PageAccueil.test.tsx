import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import type { DonneesAccueil, TypeLogementChoix } from './types'

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

const ACCUEIL: DonneesAccueil = {
  carrousel: [],
  mises_en_avant: [
    {
      reference: 'LOG-00001',
      nom: 'Villa Riviera',
      residence: 'Résidence Riviera',
      resume: 'Villa, 3 chambres',
      lieu: { commune: 'Cocody', quartier: 'Riviera Palmeraie' },
      capacite_maximale: 6,
      prix_par_nuit: 45000,
      photo: 'https://exemple.ci/villa.jpg',
      note_moyenne: null,
    },
  ],
  residences_mises_en_avant: [],
  residences_mieux_notees: [],
  bannieres: [
    { id: 1, titre: 'Offre de lancement', sous_titre: null, image: 'https://exemple.ci/banniere.jpg', lien: null },
  ],
  temoignages: [{ id: 1, nom_client: 'Aya K.', message: 'Séjour parfait.', note: 5, photo: null }],
}

const CATEGORIES: TypeLogementChoix[] = [{ id: 1, code: 'villa', nom: 'Villa', nombre_pieces: 5 }]

function repondreSelonUrl(configuration: Record<string, unknown> = { 'general.whatsapp': null }) {
  return vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
    if (url === '/accueil') return ACCUEIL as never
    if (url === '/referentiels/types-logement') return CATEGORIES as never
    if (url === '/referentiels/communes') return [] as never
    if (url === '/configuration') return configuration as never
    throw new Error(`URL non attendue dans ce test : ${url}`)
  })
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
  vi.restoreAllMocks()
  localStorage.clear()
})

describe('page d’accueil (P1-PUB-01)', () => {
  it('affiche les logements mis en avant avec leur prix', async () => {
    repondreSelonUrl()
    monter('/')

    expect(await screen.findByText('Villa Riviera')).toBeInTheDocument()
    expect(
      screen.getByText((_, element) => element?.tagName === 'SPAN' && !!element.textContent?.startsWith('Riviera Palmeraie, Cocody')),
    ).toBeInTheDocument()
    expect(screen.getByText('45 000 F / nuit')).toBeInTheDocument()
  })

  it('affiche les promotions, catégories et témoignages', async () => {
    repondreSelonUrl()
    monter('/')

    expect(await screen.findByAltText('Offre de lancement')).toBeInTheDocument()
    expect(screen.getByText('Villa')).toBeInTheDocument()
    expect(screen.getByText('« Séjour parfait. »')).toBeInTheDocument()
    expect(screen.getByText('Aya K.')).toBeInTheDocument()
  })

  it('n’affiche aucune section vide (pas de mise en avant, pas de témoignage)', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/accueil') return { mises_en_avant: [], bannieres: [], temoignages: [] } as never
      if (url === '/referentiels/types-logement') return [] as never
      if (url === '/referentiels/communes') return [] as never
      return { 'general.whatsapp': null } as never
    })
    monter('/')

    expect(await screen.findByRole('heading', { name: 'Nos applications mobiles' })).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Nos résidences et logements mis en avant' })).not.toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Ils nous font confiance' })).not.toBeInTheDocument()
  })

  it('affiche le bouton WhatsApp quand un numéro est configuré, avec un lien wa.me propre', async () => {
    repondreSelonUrl({ 'general.whatsapp': '+225 07 07 07 07 07' })
    monter('/')

    const bouton = await screen.findByRole('link', { name: 'WhatsApp' })
    expect(bouton).toHaveAttribute('href', 'https://wa.me/2250707070707')
  })

  it('n’affiche pas le bouton WhatsApp sans numéro configuré', async () => {
    repondreSelonUrl()
    monter('/')
    await screen.findByText('Villa Riviera')

    expect(screen.queryByRole('link', { name: 'WhatsApp' })).not.toBeInTheDocument()
  })

  it('affiche le carrousel d’en-tête comme le hero de l’accueil, distinct des bannières (P1-BO-10, brief refonte §14)', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/accueil') {
        return {
          ...ACCUEIL,
          carrousel: [{ id: 9, image: 'https://exemple.ci/carrousel.jpg', legende: 'Nos résidences', lien: null }],
        } as never
      }
      if (url === '/referentiels/types-logement') return CATEGORIES as never
      if (url === '/referentiels/communes') return [] as never
      return { 'general.whatsapp': null } as never
    })
    monter('/')

    // Le hero affiche la légende de la diapositive comme titre (image de fond décorative, alt vide) ;
    // les bannières restent une section distincte, plus bas dans la page.
    expect(await screen.findByRole('heading', { level: 1, name: 'Nos résidences' })).toBeInTheDocument()
    expect(document.querySelector('img[src="https://exemple.ci/carrousel.jpg"]')).toBeInTheDocument()
    expect(screen.getByAltText('Offre de lancement')).toBeInTheDocument()
  })

  it('inscrit une adresse à la lettre d’information depuis le pied de page', async () => {
    repondreSelonUrl()
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue(null as never)
    monter('/')
    await screen.findByText('Villa Riviera')

    await userEvent.type(screen.getByPlaceholderText('Votre courriel'), 'aya@exemple.ci')
    await userEvent.click(screen.getByRole('button', { name: 'S’inscrire' }))

    expect(envoyer).toHaveBeenCalledWith('/newsletter/abonnement', { email: 'aya@exemple.ci' }, 'post')
    expect(await screen.findByText('Inscription enregistrée. Merci !')).toBeInTheDocument()
  })

  it('affiche le bandeau cookies une seule fois, puis mémorise le choix', async () => {
    repondreSelonUrl()
    const { unmount } = monter('/')

    await userEvent.click(await screen.findByRole('button', { name: 'Accepter' }))
    expect(screen.queryByText('Accepter')).not.toBeInTheDocument()
    unmount()

    monter('/')
    await screen.findByText('Villa Riviera')
    expect(screen.queryByRole('button', { name: 'Accepter' })).not.toBeInTheDocument()
  })
})
