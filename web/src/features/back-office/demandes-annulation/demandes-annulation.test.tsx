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
import type { DemandeAnnulation } from './types'

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

const DEMANDE: DemandeAnnulation = {
  id: 3,
  sejour: { reference: 'SEJ-000040', arrivee: '2026-12-01', depart: '2026-12-05', net_a_payer: 150000, etat: 'confirme' },
  client: 'Awa Koné',
  motif_client: 'Empêchement professionnel de dernière minute.',
  etat: 'en_attente',
  etat_libelle: 'En attente',
  montant_retenu: null,
  montant_rembourse: null,
  motif_decision: null,
  instruite_le: null,
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

describe('back office › demandes d’annulation (P2-SEJ-06)', () => {
  it('liste les demandes puis rejette une demande en attente avec un motif', async () => {
    let rejetee = false
    const DEMANDE_REJETEE: DemandeAnnulation = { ...DEMANDE, etat: 'rejetee', etat_libelle: 'Rejetée', motif_decision: 'Politique non respectée' }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/demandes-annulation') {
        return { elements: [rejetee ? DEMANDE_REJETEE : DEMANDE], pagination: { page: 1, par_page: 5, total: 1, derniere_page: 1 } } as never
      }
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockImplementation(async () => {
      rejetee = true
      return DEMANDE_REJETEE as never
    })

    monter('/admin/demandes-annulation')

    expect(await screen.findByText('SEJ-000040')).toBeInTheDocument()
    expect(screen.getByText('Empêchement professionnel de dernière minute.')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Rejeter' }))
    const boiteDeDialogue = await screen.findByRole('dialog')
    await userEvent.type(within(boiteDeDialogue).getByRole('textbox', { name: 'Motif' }), 'Politique non respectée')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Confirmer' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/demandes-annulation/3/rejet', { motif: 'Politique non respectée' })
    expect(await screen.findByText('Rejetée')).toBeInTheDocument()
  })

  it('accepte une demande avec un mode de remboursement', async () => {
    let acceptee = false
    const DEMANDE_ACCEPTEE: DemandeAnnulation = { ...DEMANDE, etat: 'acceptee', etat_libelle: 'Acceptée' }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/demandes-annulation') {
        return { elements: [acceptee ? DEMANDE_ACCEPTEE : DEMANDE], pagination: { page: 1, par_page: 5, total: 1, derniere_page: 1 } } as never
      }
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockImplementation(async () => {
      acceptee = true
      return DEMANDE_ACCEPTEE as never
    })

    monter('/admin/demandes-annulation')

    await userEvent.click(await screen.findByRole('button', { name: 'Accepter' }))
    const boiteDeDialogue = await screen.findByRole('dialog')
    await userEvent.click(within(boiteDeDialogue).getByRole('combobox', { name: 'Mode de remboursement' }))
    await userEvent.click(await screen.findByText('Mobile money'))
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Confirmer' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/demandes-annulation/3/acceptation', { mode_de_remboursement: 'mobile_money', motif: undefined })
    expect(await screen.findByText('Acceptée')).toBeInTheDocument()
  })
})
