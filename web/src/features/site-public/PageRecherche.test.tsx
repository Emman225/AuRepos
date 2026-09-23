import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import type { CommuneChoix, EquipementChoix, QuartierChoix, ResultatsRecherche, TypeLogementChoix } from './types'

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

// jsdom n'a pas ResizeObserver, dont Select et DatePicker ont besoin.
class ResizeObserverSimule {
  observe() {}
  unobserve() {}
  disconnect() {}
}
vi.stubGlobal('ResizeObserver', ResizeObserverSimule)

const COMMUNES: CommuneChoix[] = [{ id: 1, nom: 'Cocody' }]
const QUARTIERS: QuartierChoix[] = [{ id: 11, nom: 'Riviera Palmeraie', commune_id: 1 }]
const TYPES: TypeLogementChoix[] = [{ id: 5, code: 'villa', nom: 'Villa', nombre_pieces: 5 }]
const EQUIPEMENTS: EquipementChoix[] = [
  { id: 21, nom: 'Piscine', portee: 'residence', filtre_recherche: true },
  { id: 22, nom: 'Climatisation', portee: 'logement', filtre_recherche: true },
  { id: 23, nom: 'Réservé au back office', portee: 'logement', filtre_recherche: false },
]

function resultats(total: number, avecDates = false): ResultatsRecherche {
  return {
    avec_dates: avecDates,
    arrivee: '2026-11-10',
    depart: '2026-11-11',
    elements:
      total === 0
        ? []
        : [
            {
              reference: 'LOG-00001',
              nom: 'Villa Riviera',
              residence: 'Résidence Riviera',
              resume: 'Villa, 3 chambres',
              lieu: { commune: 'Cocody', quartier: 'Riviera Palmeraie' },
              capacite_maximale: 6,
              prix_par_nuit: 65000,
              photo: null,
              note_moyenne: null,
            },
          ],
    pagination: { page: 1, par_page: 24, total, derniere_page: 1 },
  }
}

function repondre() {
  return vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
    if (url === '/referentiels/communes') return COMMUNES as never
    if (url === '/referentiels/quartiers') return QUARTIERS as never
    if (url === '/referentiels/types-logement') return TYPES as never
    if (url === '/referentiels/equipements') return EQUIPEMENTS as never
    if (url === '/catalogue/recherche') return resultats(1) as never
    if (url === '/configuration') return { 'general.whatsapp': null } as never
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
})

describe('recherche publique (P1-PUB-02, P1-PUB-03)', () => {
  it('affiche « ce qui est libre ce soir » sans filtre, avec la vignette du logement', async () => {
    repondre()
    monter('/recherche')

    expect(await screen.findByText('1 logement libre ce soir')).toBeInTheDocument()
    expect(screen.getByText('Villa Riviera')).toBeInTheDocument()
    expect(screen.getByText('65 000 F / nuit')).toBeInTheDocument()
  })

  it('affiche un message dédié quand rien ne correspond', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/catalogue/recherche') return resultats(0) as never
      if (url === '/referentiels/communes') return COMMUNES as never
      if (url === '/referentiels/quartiers') return QUARTIERS as never
      if (url === '/referentiels/types-logement') return TYPES as never
      if (url === '/referentiels/equipements') return EQUIPEMENTS as never
      return { 'general.whatsapp': null } as never
    })
    monter('/recherche')

    expect(await screen.findByText('Aucun logement ne correspond à cette recherche.')).toBeInTheDocument()
  })

  it('ne propose en filtre que les équipements marqués « filtre_recherche »', async () => {
    repondre()
    monter('/recherche')

    expect(await screen.findByText('Piscine')).toBeInTheDocument()
    expect(screen.getByText('Climatisation')).toBeInTheDocument()
    expect(screen.queryByText('Réservé au back office')).not.toBeInTheDocument()
  })

  it('charge les quartiers de la commune choisie, pas toutes les communes', async () => {
    const lire = repondre()
    monter('/recherche')
    await screen.findByText('1 logement libre ce soir')

    await userEvent.click(screen.getByRole('combobox', { name: 'Commune' }))
    await userEvent.click(await screen.findByText('Cocody'))

    await waitFor(() => expect(lire).toHaveBeenCalledWith('/referentiels/quartiers', { commune_id: 1 }))
  })

  it('pré-remplit le filtre type de logement depuis l’adresse (venant des catégories de l’accueil)', async () => {
    const lire = repondre()
    monter('/recherche?type_logement_id=5')

    await waitFor(() =>
      expect(lire).toHaveBeenCalledWith('/catalogue/recherche', expect.objectContaining({ type_logement_id: 5 })),
    )
  })

  it('relance la recherche avec les filtres choisis et remet la page à zéro', async () => {
    const lire = repondre()
    monter('/recherche?page=2')
    await screen.findByText('1 logement libre ce soir')
    lire.mockClear()

    await userEvent.click(screen.getByRole('spinbutton', { name: 'Budget max / nuit' }))
    await userEvent.type(screen.getByRole('spinbutton', { name: 'Budget max / nuit' }), '80000')
    await userEvent.click(screen.getByRole('button', { name: 'Rechercher' }))

    await waitFor(() =>
      expect(lire).toHaveBeenCalledWith(
        '/catalogue/recherche',
        expect.objectContaining({ budget_max: 80000, page: undefined }),
      ),
    )
  })
})
