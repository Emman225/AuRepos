import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
import type { TableauDeBordChauffeur, TransfertChauffeur } from './types'

Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

const chauffeur = (): Utilisateur => ({
  id: 1,
  nom: 'Kouassi',
  prenoms: 'Serge',
  nom_complet: 'Serge Kouassi',
  email: 'serge@exemple.ci',
  telephone: null,
  profil: 'chauffeur',
  profil_libelle: 'Chauffeur',
  espace: 'chauffeur',
  agence: null,
  peut_encaisser: false,
})

const COMPTEURS: TableauDeBordChauffeur = { nombre_transferts_affectes: 1, total_gagne: 15000, solde_du: 6000 }

const TRANSFERT_AFFECTE: TransfertChauffeur = {
  reference: 'TRF-000006',
  sejour_reference: 'SEJ-000001',
  lieu_de_prise_en_charge: 'Aéroport FHB',
  commune: 'Cocody',
  date_heure_prevue: '10/11/2026 14:00',
  nombre_passagers: 2,
  nombre_bagages: 1,
  etat: 'affecte',
  etat_libelle: 'Affecté',
  vehicule: 'CI-1234-AB',
  montant_verse_au_chauffeur: 5000,
}

const TRANSFERT_TERMINE: TransfertChauffeur = { ...TRANSFERT_AFFECTE, etat: 'termine', etat_libelle: 'Terminé' }

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
  useSession.setState({ jeton: 'jeton-test', utilisateur: chauffeur(), statut: 'connecte' })
})

describe('espace chauffeur (CdC § 6.6)', () => {
  it('affiche le tableau de bord puis clôture un transfert affecté avec le code communiqué par le client', async () => {
    let transfertsAppeles = 0
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/chauffeur/tableau-de-bord') return COMPTEURS as never
      if (url === '/chauffeur/transferts') {
        transfertsAppeles += 1
        return [transfertsAppeles === 1 ? TRANSFERT_AFFECTE : TRANSFERT_TERMINE] as never
      }
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue(TRANSFERT_TERMINE as never)

    monter('/chauffeur')

    expect(await screen.findByText('Bonjour Serge')).toBeInTheDocument()
    expect(await screen.findByText('15 000 F')).toBeInTheDocument()
    expect(screen.getByText('6 000 F')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes transferts'))
    expect(await screen.findByText('Aéroport FHB')).toBeInTheDocument()
    expect(screen.getByText('Affecté')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Clôturer avec le code' }))
    await userEvent.type(await screen.findByLabelText('Code de prise en charge'), '384920')
    await userEvent.click(screen.getAllByRole('button', { name: 'Clôturer avec le code' })[1])

    await waitFor(() => expect(envoyer).toHaveBeenCalledWith('/chauffeur/transferts/6/cloturer', { code: '384920' }))
    expect(await screen.findByText('Terminé')).toBeInTheDocument()
  })
})
