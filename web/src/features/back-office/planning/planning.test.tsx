import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import dayjs from 'dayjs'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../../routes/RoutesApplication'
import * as clientApi from '../../../shared/api/client'
import '../../../shared/i18n'
import { useSession } from '../../auth/session'
import type { Utilisateur } from '../../auth/types'
import type { DonneesPlanning } from './types'

Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

const administrateur = (): Utilisateur => ({
  id: 1,
  nom: 'Diallo',
  prenoms: 'Moussa',
  nom_complet: 'Moussa Diallo',
  email: 'moussa@exemple.ci',
  telephone: null,
  profil: 'administrateur',
  profil_libelle: 'Administrateur',
  espace: 'backoffice',
  agence: null,
  peut_encaisser: true,
})

function monter(adresse: string) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[adresse]}>
        <RoutesApplication />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeEach(() => {
  vi.restoreAllMocks()
  useSession.setState({ jeton: 'jeton-test', utilisateur: administrateur(), statut: 'connecte' })
})

describe('planning back office (P1-BO)', () => {
  it('affiche la grille logements × jours avec les séjours en cours de fenêtre', async () => {
    const arrivee = dayjs().add(1, 'day').format('YYYY-MM-DD')
    const depart = dayjs().add(3, 'day').format('YYYY-MM-DD')
    const donnees: DonneesPlanning = {
      logements: [{ id: 1, reference: 'LOG-00001', nom: 'Villa Riviera', residence: { id: 3, nom: 'Riviera Palmeraie' } }],
      sejours: [
        {
          id: 42,
          reference: 'SEJ-000042',
          logement_id: 1,
          etat: 'confirme',
          etat_libelle: 'Confirmé',
          client_nom: 'Awa Koné',
          arrivee,
          depart,
        },
      ],
    }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/planning') return donnees as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/planning')

    expect(await screen.findByText('Villa Riviera')).toBeInTheDocument()
    expect(screen.getByText('Riviera Palmeraie')).toBeInTheDocument()

    const barre = screen.getByText('Awa Koné').closest('a')
    expect(barre).toHaveAttribute('href', '/admin/reservations/42')
  })

  it("affiche un état vide quand aucun logement n'existe sur la période", async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/planning') return { logements: [], sejours: [] } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/planning')

    expect(await screen.findByText('Aucun logement à afficher sur cette période.')).toBeInTheDocument()
  })
})
