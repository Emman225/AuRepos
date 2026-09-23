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
import type { Commande, CompteursLivreur, GainsLivreur } from './types'

Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

const livreur = (): Utilisateur => ({
  id: 1,
  nom: 'Yao',
  prenoms: 'Kouadio',
  nom_complet: 'Kouadio Yao',
  email: 'kouadio@exemple.ci',
  telephone: null,
  profil: 'livreur',
  profil_libelle: 'Livreur',
  espace: 'livreur',
  agence: null,
  peut_encaisser: false,
})

const COMPTEURS: CompteursLivreur = { courses_en_cours: 1, courses_livrees: 3, gains: { total_gagne: 6000, deja_verse: 4000, solde_du: 2000 } }

const GAINS: GainsLivreur = { total_gagne: 6000, deja_verse: 4000, solde_du: 2000 }

const COURSES: Commande[] = [
  {
    id: 1,
    reference: 'CR-000001',
    sejour_id: 1,
    sejour_reference: 'SEJ-000001',
    restaurateur_id: 1,
    restaurateur: 'Chez Awa',
    etat: 'en_livraison',
    etat_libelle: 'En livraison',
    mode_reglement: 'note_du_sejour',
    montant_total: 4800,
    livreur_id: 1,
    livreur: 'Kouadio Yao',
    remuneration_livreur: 1000,
    code_livraison_emis: true,
    notes: null,
    lignes: [],
    cree_le: null,
  },
]

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
  useSession.setState({ jeton: 'jeton-test', utilisateur: livreur(), statut: 'connecte' })
})

describe('espace livreur', () => {
  it('affiche le tableau de bord puis navigue vers les courses et les gains', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/livreur/tableau-de-bord') return COMPTEURS as never
      if (url === '/livreur/courses') return COURSES as never
      if (url === '/livreur/gains') return GAINS as never
      return { 'general.whatsapp': null } as never
    })

    monter('/livreur')

    expect(await screen.findByText('Bonjour Kouadio')).toBeInTheDocument()
    expect(await screen.findByText('2 000 F')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes courses'))
    expect(await screen.findByText('CR-000001')).toBeInTheDocument()
    expect(screen.getByText('Clôturer avec le code')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes gains'))
    expect(await screen.findByText('6 000 F')).toBeInTheDocument()
    expect(screen.getByText('4 000 F')).toBeInTheDocument()
  })

  it('clôture une course en saisissant le code de livraison, et affiche l’erreur si le code est faux', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/livreur/courses') return COURSES as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockRejectedValue(new clientApi.ErreurApi('Code incorrect. Il reste 4 essai(s).', 422))

    monter('/livreur/courses')

    await userEvent.click(await screen.findByRole('button', { name: 'Clôturer avec le code' }))
    await userEvent.type(await screen.findByLabelText('Code de livraison'), '1234')

    const modale = screen.getByRole('dialog')
    await userEvent.click(within(modale).getByRole('button', { name: 'Clôturer avec le code' }))

    expect(await screen.findByText('Code incorrect. Il reste 4 essai(s).')).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith('/livreur/courses/1/cloture', { code: '1234' })
  })
})
