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
import type { Facture } from './types'

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

const FACTURE_A_TRANSMETTRE: Facture = {
  id: 7,
  numero: 'FAC-2026-007',
  type: 'facture',
  type_libelle: 'Facture',
  sejour: { id: 42, reference: 'SEJ-000042', arrivee: '10/11/2026', depart: '13/11/2026' },
  client: { id: 5, nom: 'Awa Koné', email: 'awa@exemple.ci' },
  facture_origine: null,
  motif_avoir: null,
  montant_ht: 90000,
  montant_tva: 16200,
  autres_taxes: 3900,
  montant_ttc: 110100,
  lignes: [
    { description: 'Hébergement', quantity: 3, amount: 30000, taxes: ['TVA'] },
    { description: 'Taxe de séjour', quantity: 1, amount: 3000, taxes: [] },
  ],
  statut_transmission: 'a_transmettre',
  statut_transmission_libelle: 'À transmettre',
  reference_dgi: null,
  token_qr: null,
  ncc_dgi: null,
  solde_stickers: null,
  motif_refus_dgi: null,
  transmise_par: null,
  transmise_le: null,
  cree_le: '22/09/2026 10:00:00',
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

describe('factures back office (P1-BO-08)', () => {
  it('liste les factures et transmet une facture à la DGI depuis la fiche', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/factures') return { elements: [FACTURE_A_TRANSMETTRE], pagination: { page: 1, par_page: 50, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi
      .spyOn(clientApi, 'envoyer')
      .mockResolvedValue({ ...FACTURE_A_TRANSMETTRE, statut_transmission: 'transmise', reference_dgi: 'FNE-REF-0001', solde_stickers: 998 } as never)

    monter('/admin/factures')

    expect(await screen.findByText('FAC-2026-007')).toBeInTheDocument()
    await userEvent.click(screen.getByText('FAC-2026-007'))

    await userEvent.click(await screen.findByRole('button', { name: 'Transmettre à la DGI' }))

    expect(await screen.findByText(/FNE-REF-0001/)).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith('/backoffice/factures/7/transmission', undefined, 'put')
  })

  it('génère une nouvelle facture depuis un séjour', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/factures') return { elements: [], pagination: { page: 1, par_page: 50, total: 0, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue(FACTURE_A_TRANSMETTRE as never)

    monter('/admin/factures')
    await screen.findByRole('heading', { name: 'Factures normalisées' })
    await userEvent.click(screen.getByRole('button', { name: 'Nouvelle facture' }))

    const [sejourIdInput] = await screen.findAllByRole('spinbutton')
    await userEvent.type(sejourIdInput, '42')
    await userEvent.click(screen.getByRole('button', { name: 'Générer' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/factures', { sejour_id: 42, type: 'proforma' })
  })

  it('exige un motif avant d’émettre un avoir', async () => {
    const factureTransmise: Facture = { ...FACTURE_A_TRANSMETTRE, statut_transmission: 'transmise', reference_dgi: 'FNE-REF-0001' }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/factures') return { elements: [factureTransmise], pagination: { page: 1, par_page: 50, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/factures')
    await screen.findByText('FAC-2026-007')
    await userEvent.click(screen.getByText('FAC-2026-007'))

    await userEvent.click(await screen.findByRole('button', { name: 'Émettre un avoir' }))
    const boutons = await screen.findAllByRole('button', { name: 'Émettre un avoir' })
    expect(boutons.at(-1)).toBeDisabled()
  })
})
