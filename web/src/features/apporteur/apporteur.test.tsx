import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
import type { CommissionApporteur, CompteursApporteur, Filleul } from './types'

Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

const apporteur = (): Utilisateur => ({
  id: 1,
  nom: 'Konan',
  prenoms: 'Ahou',
  nom_complet: 'Ahou Konan',
  email: 'ahou@exemple.ci',
  telephone: null,
  profil: 'apporteur',
  profil_libelle: 'Apporteur d’affaires',
  espace: 'apporteur',
  agence: null,
  peut_encaisser: false,
})

const COMPTEURS: CompteursApporteur = { nombre_filleuls: 1, nombre_commissions: 2, solde_du: 3000 }
const FILLEULS: Filleul[] = [{ id: 5, nom_complet: 'Filleul Test', inscrit_le: '23/09/2026' }]
const COMMISSIONS: CommissionApporteur[] = [
  { id: 1, sejour_reference: 'SEJ-000001', montant: 3000, cree_le: '20/09/2026 10:00:00' },
  { id: 2, sejour_reference: 'SEJ-000002', montant: 7000, cree_le: '22/09/2026 09:00:00' },
]

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
  useSession.setState({ jeton: 'jeton-test', utilisateur: apporteur(), statut: 'connecte' })
})

describe('espace apporteur d’affaires', () => {
  it('affiche le tableau de bord puis navigue vers les filleuls et les commissions', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/apporteur/tableau-de-bord') return COMPTEURS as never
      if (url === '/apporteur/filleuls') return FILLEULS as never
      if (url === '/apporteur/commissions') return COMMISSIONS as never
      return { 'general.whatsapp': null } as never
    })

    monter('/apporteur')

    expect(await screen.findByText('Bonjour Ahou')).toBeInTheDocument()
    expect(await screen.findByText('3 000 F')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes filleuls'))
    expect(await screen.findByText('Filleul Test')).toBeInTheDocument()
    expect(screen.getByText('23/09/2026')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes commissions'))
    expect(await screen.findByText('SEJ-000001')).toBeInTheDocument()
    expect(screen.getByText('3 000 F')).toBeInTheDocument()
    expect(screen.getByText('7 000 F')).toBeInTheDocument()
  })
})
