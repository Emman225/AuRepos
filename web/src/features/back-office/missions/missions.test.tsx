import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../../routes/RoutesApplication'
import * as clientApi from '../../../shared/api/client'
import '../../../shared/i18n'
import { useSession } from '../../auth/session'
import type { Utilisateur } from '../../auth/types'
import type { Mission } from './types'

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

const MISSION_A_FAIRE: Mission = {
  id: 1,
  type: 'apres_depart',
  type_libelle: 'Après départ',
  origine: 'automatique',
  origine_libelle: 'Automatique',
  statut: 'a_faire',
  statut_libelle: 'À faire',
  logement: { id: 4, nom: 'Villa Riviera', residence: 'Riviera Palmeraie' },
  sejour: { reference: 'SEJ-000010', arrivee: '2026-11-01', depart: '2026-11-05' },
  agent: null,
  echeance: '2026-11-05 12:00:00',
  notes: null,
  debutee_le: null,
  terminee_le: null,
  created_at: '01/11/2026 08:00:00',
}

const MISSION_AFFECTEE: Mission = {
  ...MISSION_A_FAIRE,
  id: 2,
  type: 'menage_demande',
  type_libelle: 'Ménage demandé',
  statut: 'en_cours',
  statut_libelle: 'En cours',
  agent: 'Fatou Traoré',
  sejour: null,
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

describe('back office › missions de ménage (P2-MEN-01)', () => {
  it('liste les missions puis affecte un agent à une mission à faire', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/missions') {
        return { elements: [MISSION_A_FAIRE, MISSION_AFFECTEE], pagination: { page: 1, par_page: 5, total: 2, derniere_page: 1 } } as never
      }
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...MISSION_A_FAIRE, agent: 'Fatou Traoré' } as never)

    monter('/admin/missions')

    expect(await screen.findByText('Après départ')).toBeInTheDocument()
    expect(screen.getByText('SEJ-000010')).toBeInTheDocument()
    expect(screen.getByText('Fatou Traoré')).toBeInTheDocument()
    expect(screen.getByText('Non affectée')).toBeInTheDocument()

    const ligne = screen.getByText('SEJ-000010').closest('tr')!
    await userEvent.click(within(ligne).getByRole('button', { name: 'Affecter' }))

    await userEvent.type(await screen.findByLabelText('Identifiant du compte agent'), '7')
    const boiteDeDialogue = await screen.findByRole('dialog')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Affecter' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/missions/1/affectation', { agent_id: 7 }, 'put')
  })

  it('ouvre le formulaire de demande de ménage', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/missions') return { elements: [], pagination: { page: 1, par_page: 5, total: 0, derniere_page: 1 } } as never
      if (url === '/backoffice/residences') {
        return { elements: [{ id: 1, nom: 'Riviera Palmeraie' }], pagination: { page: 1, par_page: 100, total: 1, derniere_page: 1 } } as never
      }
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/missions')

    await userEvent.click(await screen.findByRole('button', { name: 'Demander un ménage' }))
    expect(await screen.findByRole('combobox', { name: 'Résidence' })).toBeInTheDocument()
  })
})
