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
import type { Agence, Personnel } from './types'

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

const GESTIONNAIRE: Personnel = {
  id: 8,
  nom: 'Bamba',
  prenoms: 'Fatou',
  nom_complet: 'Fatou Bamba',
  email: 'fatou@dalakoun.ci',
  telephone: '0707070707',
  identifiant: 'fbamba',
  profil: 'gestionnaire',
  profil_libelle: 'Gestionnaire',
  statut: 'actif',
  agence: { id: 3, nom: 'Agence Plateau' },
  residences: [{ id: 1, nom: 'Résidence Les Palmiers' }],
  cree_le: '01/09/2026 10:00:00',
}

const AGENCE: Agence = {
  id: 3,
  nom: 'Agence Plateau',
  adresse: 'Plateau, Abidjan',
  telephone: '2720000000',
  active: true,
  nombre_utilisateurs: 2,
  cree_le: '01/09/2026 10:00:00',
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

describe('personnel et agences (P1-BO-09)', () => {
  it('liste le personnel avec son agence et ses résidences confiées', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/personnel') return { elements: [GESTIONNAIRE], pagination: { page: 1, par_page: 50, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/personnel')

    expect(await screen.findByText('Fatou Bamba')).toBeInTheDocument()
    expect(screen.getByText('Agence Plateau')).toBeInTheDocument()
    expect(within(screen.getByRole('row', { name: /Fatou Bamba/ })).getByText('1')).toBeInTheDocument()
  })

  it('désactive la création tant que les champs obligatoires ne sont pas remplis', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/personnel') return { elements: [], pagination: { page: 1, par_page: 50, total: 0, derniere_page: 1 } } as never
      if (url === '/backoffice/agences') return { elements: [AGENCE], pagination: { page: 1, par_page: 100, total: 1, derniere_page: 1 } } as never
      if (url === '/backoffice/residences') return { elements: [], pagination: { page: 1, par_page: 100, total: 0, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/personnel')
    await screen.findByRole('heading', { name: 'Personnel et agences' })
    await userEvent.click(screen.getByText('Nouveau compte'))

    const bouton = await screen.findByRole('button', { name: 'Créer le compte' })
    expect(bouton).toBeDisabled()

    const champs = await screen.findAllByRole('textbox')
    await userEvent.type(champs[0], 'Koffi')
    expect(bouton).toBeDisabled()
  })

  it('liste les agences et crée une nouvelle agence', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/agences') return { elements: [AGENCE], pagination: { page: 1, par_page: 100, total: 1, derniere_page: 1 } } as never
      if (url === '/backoffice/personnel') return { elements: [], pagination: { page: 1, par_page: 50, total: 0, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...AGENCE, id: 4, nom: 'Agence Cocody' } as never)

    monter('/admin/personnel')
    await screen.findByRole('heading', { name: 'Personnel et agences' })
    await userEvent.click(screen.getByText('Agences'))

    expect(await screen.findByText('Agence Plateau')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Nouvelle agence'))
    const champsNom = await screen.findAllByRole('textbox')
    await userEvent.type(champsNom[0], 'Agence Cocody')

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/agences', { nom: 'Agence Cocody', adresse: undefined, telephone: undefined, active: true })
  })
})
