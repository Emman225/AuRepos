import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
import type { CompteursDuJour } from './types'

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

describe('tableau de bord back office (P1-BO-01)', () => {
  it('affiche les compteurs du jour, avec un lien correct vers les réservations', async () => {
    const compteurs: CompteursDuJour = {
      reservations_en_attente: 3,
      arrivees_du_jour: 2,
      departs_du_jour: 1,
      sejours_en_cours: 5,
      devis_en_attente: 4,
    }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/tableau-de-bord') return compteurs as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin')

    expect(await screen.findByText('Bonjour Moussa')).toBeInTheDocument()
    expect(await screen.findByText('3')).toBeInTheDocument()
    expect(screen.getByText('Réservations en attente')).toBeInTheDocument()
    expect(screen.getByText('Arrivées du jour')).toBeInTheDocument()
    expect(screen.getByText('Départs du jour')).toBeInTheDocument()
    expect(screen.getByText('Séjours en cours')).toBeInTheDocument()
    expect(screen.getByText('Devis en attente')).toBeInTheDocument()

    // Chaque compteur mène à la file DÉJÀ FILTRÉE, pas à la liste générale (brief refonte §7) :
    // un lien relatif depuis une route INDEX (« .. ») remonterait trop loin, donc chaque lien
    // reste un chemin absolu SANS « .. », avec l'onglet correspondant en paramètre.
    const hrefs = screen.getAllByRole('link').map((a) => a.getAttribute('href'))
    expect(hrefs).toContain('/admin/reservations?onglet=en_attente')
    expect(hrefs).toContain('/admin/reservations?onglet=arrivees')
    expect(hrefs).toContain('/admin/reservations?onglet=departs')
    expect(hrefs).toContain('/admin/reservations?onglet=en_cours')
    expect(hrefs).toContain('/admin/reservations?onglet=devis')
  })
})
