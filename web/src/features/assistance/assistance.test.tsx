import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
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

const TICKET_OUVERT: TicketAssistance = {
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

const agentAssistance = (): Utilisateur => ({
  id: 9,
  nom: 'Bamba',
  prenoms: 'Salif',
  nom_complet: 'Salif Bamba',
  email: 'salif@exemple.ci',
  telephone: null,
  profil: 'agent_assistance',
  profil_libelle: 'Agent d’assistance',
  espace: 'assistance',
  agence: null,
  peut_encaisser: false,
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
  useSession.setState({ jeton: 'jeton-test', utilisateur: agentAssistance(), statut: 'connecte' })
})

describe('espace assistance (CdC § 6.1, P2-AST-01)', () => {
  it('liste les tickets, répond à un ticket ouvert puis le ferme', async () => {
    const ticketRepondu: TicketAssistance = { ...TICKET_OUVERT, statut: 'en_cours', statut_libelle: 'En cours', reponse: 'Un technicien passe dans l’heure.' }
    let appels = 0
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/assistance/tickets') {
        appels += 1
        return [appels === 1 ? TICKET_OUVERT : ticketRepondu] as never
      }
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockImplementation(async (url: string) => {
      if (url === '/assistance/tickets/1/reponse') return ticketRepondu as never
      if (url === '/assistance/tickets/1/fermeture') return { ...ticketRepondu, statut: 'ferme', statut_libelle: 'Fermé' } as never
      throw new Error(`URL inattendue : ${url}`)
    })

    monter('/assistance')

    expect(await screen.findByText('Bonjour Salif')).toBeInTheDocument()
    expect(await screen.findByText('Climatisation en panne')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Climatisation en panne').closest('tr')!)

    const tiroir = await screen.findByRole('dialog')
    expect(within(tiroir).getByText('La climatisation ne démarre plus depuis hier soir.')).toBeInTheDocument()

    await userEvent.type(within(tiroir).getByLabelText('Votre réponse'), 'Un technicien passe dans l’heure.')
    await userEvent.click(within(tiroir).getByRole('button', { name: 'Répondre' }))

    expect(envoyer).toHaveBeenCalledWith('/assistance/tickets/1/reponse', { reponse: 'Un technicien passe dans l’heure.' })
    expect(await within(tiroir).findByText('En cours')).toBeInTheDocument()

    await userEvent.click(within(tiroir).getByRole('button', { name: 'Fermer' }))
    expect(envoyer).toHaveBeenCalledWith('/assistance/tickets/1/fermeture', { reponse: undefined })
  })

  it('filtre les tickets par statut', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string, params?: Record<string, unknown>) => {
      if (url === '/assistance/tickets') {
        if (params?.statut === 'ferme') return [] as never
        return [TICKET_OUVERT] as never
      }
      return { 'general.whatsapp': null } as never
    })

    monter('/assistance')
    expect(await screen.findByText('Climatisation en panne')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('combobox'))
    await userEvent.click(await screen.findByText('Fermé'))

    expect(await screen.findByText('Aucun ticket d’assistance pour l’instant.')).toBeInTheDocument()
  })
})
