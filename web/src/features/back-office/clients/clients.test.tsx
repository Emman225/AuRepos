import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../../routes/RoutesApplication'
import * as clientApi from '../../../shared/api/client'
import '../../../shared/i18n'
import { useSession } from '../../auth/session'
import type { Utilisateur } from '../../auth/types'
import type { Client, ClientATerme } from './types'

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

const CLIENT: Client = {
  id: 5,
  nom: 'Koné',
  prenoms: 'Awa',
  nom_complet: 'Awa Koné',
  email: 'awa@exemple.ci',
  telephone: '0707070707',
  statut: 'actif',
  statut_libelle: 'Actif',
  nature: 'b2c',
  nature_libelle: 'Particulier',
  raison_sociale: null,
  ncc: null,
  rccm: null,
  tva_hebergement: true,
  tva_transfert: true,
  tva_motif: null,
  tva_motif_le: null,
  statut_a_terme: 'aucune',
  statut_a_terme_libelle: 'Sans demande',
  plafond_credit: null,
  liste_noire: false,
  liste_noire_motif: null,
  liste_noire_le: null,
  cree_le: '01/09/2026 10:00:00',
}

const DEMANDE_A_TERME: ClientATerme = {
  client_id: 12,
  utilisateur: { id: 40, nom: 'ACME SARL — Koffi', email: 'koffi@acme.ci', telephone: null },
  nature: 'b2b',
  nature_libelle: 'Entreprise',
  raison_sociale: 'ACME SARL',
  statut: 'en_attente',
  statut_libelle: 'En attente d’instruction',
  plafond_credit: null,
  encours: null,
  demande_le: '20/09/2026 09:00:00',
  motif_refus: null,
  traite_le: null,
  pieces: [],
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

describe('clients back office (P1-BO-07)', () => {
  it('liste les clients et bascule la TVA depuis la fiche', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/clients') return { elements: [CLIENT], pagination: { page: 1, par_page: 50, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...CLIENT, tva_hebergement: false } as never)

    monter('/admin/clients')

    expect(await screen.findByText('Awa Koné')).toBeInTheDocument()
    await userEvent.click(screen.getByText('Awa Koné'))

    await userEvent.type(screen.getByPlaceholderText('Motif de la bascule (obligatoire avant de modifier)'), 'Demande écrite du client.')
    const bascules = await screen.findAllByRole('switch')
    await userEvent.click(bascules[0])

    expect(envoyer).toHaveBeenCalledWith(
      '/backoffice/clients/5/tva',
      { tva_hebergement: false, motif: 'Demande écrite du client.' },
      'put',
    )
  })

  it('exige un motif avant de mettre un client en liste noire', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/clients') return { elements: [CLIENT], pagination: { page: 1, par_page: 50, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/clients')
    await screen.findByText('Awa Koné')
    await userEvent.click(screen.getByText('Awa Koné'))

    const bouton = await screen.findByRole('button', { name: 'Mettre en liste noire' })
    expect(bouton).toBeDisabled()

    await userEvent.type(screen.getByPlaceholderText('Motif (obligatoire)'), 'Impayés répétés.')
    expect(bouton).not.toBeDisabled()
  })

  it('liste les demandes de compte à terme en attente et accepte un dossier', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/clients-a-terme') return { elements: [DEMANDE_A_TERME], pagination: { page: 1, par_page: 50, total: 1, derniere_page: 1 } } as never
      if (url === '/backoffice/clients-a-terme/40/pieces') return [] as never
      if (url === '/backoffice/clients') return { elements: [], pagination: { page: 1, par_page: 50, total: 0, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...DEMANDE_A_TERME, statut: 'acceptee' } as never)

    monter('/admin/clients')
    await screen.findByRole('heading', { name: 'Clients' })
    await userEvent.click(screen.getByText('Demandes de compte à terme'))

    expect(await screen.findByText('ACME SARL — Koffi')).toBeInTheDocument()
    await userEvent.click(screen.getByText('ACME SARL — Koffi'))

    await userEvent.click(await screen.findByRole('button', { name: 'Accepter' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/clients-a-terme/40/decision', { decision: 'accepter', plafond_credit: 0, motif: undefined }, 'put')
  })
})
