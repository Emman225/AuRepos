import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import { ErreurApi } from '../../shared/api/client'
import '../../shared/i18n'
import type { LogementFiche } from './types'

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

const FICHE: LogementFiche = {
  reference: 'LOG-00001',
  nom: 'Villa Riviera',
  resume: 'Villa, 3 chambres',
  type: { code: 'villa', nom: 'Villa' },
  residence: { nom: 'Résidence Riviera', slug: 'residence-riviera', description: null },
  lieu: { commune: 'Cocody', quartier: 'Riviera Palmeraie' },
  nombre_pieces: 5,
  nombre_chambres: 3,
  nombre_lits: 4,
  nombre_salles_de_bain: 2,
  capacite_de_base: 4,
  capacite_maximale: 6,
  surface_m2: 150,
  description: 'Une villa spacieuse avec jardin.',
  regles: { fumeur_autorise: false, animaux_autorises: true, fetes_autorisees: false, texte: null },
  heure_arrivee: '14:00',
  heure_depart: '12:00',
  prix_par_nuit: 65000,
  devise: 'F',
  caution: 100000,
  duree_minimale: 2,
  duree_maximale: null,
  politique_annulation: 'moderee',
  politique_annulation_libelle: 'Modérée',
  equipements: [{ nom: 'Piscine', icone: null, portee: 'residence' }],
  photos: [
    { url: 'https://exemple.ci/1.jpg', url_vignette: 'https://exemple.ci/1v.jpg', legende: 'Salon', couverture: true },
  ],
  tarifs_par_saison: [
    { saison: 'Haute saison', categorie: 'haute', debut: '2026-07-01', fin: '2026-12-31', tarif: 75000 },
  ],
  note_moyenne: null,
  avis: [],
  similaires: [
    {
      reference: 'LOG-00002',
      nom: 'Villa Voisine',
      residence: 'Résidence Riviera',
      resume: 'Villa, 2 chambres',
      lieu: { commune: 'Cocody', quartier: 'Angré 8e Tranche' },
      capacite_maximale: 4,
      prix_par_nuit: 55000,
      photo: null,
      note_moyenne: null,
    },
  ],
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

describe('fiche logement (P1-PUB-04)', () => {
  it('affiche le logement avec ses sections (équipements, règles, tarifs, similaires)', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001') return FICHE as never
      if (url === '/catalogue/logements/LOG-00001/disponibilite') return { mois: '2026-11', jours_occupes: [] } as never
      return { 'general.whatsapp': null } as never
    })
    monter('/logements/LOG-00001')

    expect(await screen.findByRole('heading', { name: 'Villa Riviera' })).toBeInTheDocument()
    expect(screen.getByText('Piscine')).toBeInTheDocument()
    expect(screen.getByText('Animaux autorisés')).toBeInTheDocument()
    expect(screen.getByText('Haute saison')).toBeInTheDocument()
    expect(screen.getByText('75 000 F / nuit')).toBeInTheDocument()
    expect(screen.getByText('Aucun avis pour l’instant.')).toBeInTheDocument()
    expect(screen.getByText('Villa Voisine')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Riviera Palmeraie, Cocody/ })).toHaveAttribute(
      'href',
      expect.stringContaining('google.com/maps'),
    )
  })

  it('affiche un message dédié pour un logement introuvable, sans casser la page', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/INEXISTANT') throw new ErreurApi('Introuvable', 404)
      return { 'general.whatsapp': null } as never
    })
    monter('/logements/INEXISTANT')

    expect(await screen.findByText('Ce logement n’existe pas ou n’est plus publié.')).toBeInTheDocument()
  })

  it('renvoie vers la fiche depuis une vignette (accueil, recherche, similaires)', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001') return FICHE as never
      if (url === '/catalogue/logements/LOG-00001/disponibilite') return { mois: '2026-11', jours_occupes: [] } as never
      return { 'general.whatsapp': null } as never
    })
    monter('/logements/LOG-00001')

    await screen.findByRole('heading', { name: 'Villa Riviera' })
    expect(screen.getByRole('link', { name: /Villa Voisine/ })).toHaveAttribute('href', '/logements/LOG-00002')
  })
})
