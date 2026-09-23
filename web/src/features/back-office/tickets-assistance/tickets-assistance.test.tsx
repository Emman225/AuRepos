import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../../routes/RoutesApplication'
import * as clientApi from '../../../shared/api/client'
import '../../../shared/i18n'
import { useSession } from '../../auth/session'
import type { Utilisateur } from '../../auth/types'
import type { TicketAssistance } from './types'

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

const TICKET: TicketAssistance = {
  id: 1,
  sejour: { reference: 'SEJ-000020', logement: 'Villa Riviera' },
  client: 'Awa Koné',
  sujet: 'Climatisation en panne',
  message: 'La climatisation ne démarre plus depuis hier soir.',
  statut: 'ouvert',
  statut_libelle: 'Ouvert',
  reponse: null,
  traite_le: null,
  created_at: '10/11/2026 09:00:00',
}

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
    <AppAntd>
      <QueryClientProvider client={queryClient}>
        <MemoryRouter initialEntries={[adresse]}>
          <RoutesApplication />
        </MemoryRouter>
      </QueryClientProvider>
    </AppAntd>,
  )
}

beforeEach(() => {
  vi.restoreAllMocks()
  useSession.setState({ jeton: 'jeton-test', utilisateur: administrateur(), statut: 'connecte' })
})

describe('back office › tickets d’assistance (P2-AST-01, consultation seule)', () => {
  it('liste les tickets sans proposer aucune action', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/tickets-assistance') {
        return { elements: [TICKET], pagination: { page: 1, par_page: 5, total: 1, derniere_page: 1 } } as never
      }
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/tickets-assistance')

    expect(await screen.findByText('Climatisation en panne')).toBeInTheDocument()
    expect(screen.getByText('SEJ-000020')).toBeInTheDocument()
    expect(screen.getByText('Ouvert')).toBeInTheDocument()
    expect(screen.getByText(/la réponse à un ticket se fait dans l’espace assistance/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /répondre|fermer/i })).not.toBeInTheDocument()
  })
})
