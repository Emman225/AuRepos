import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../../routes/RoutesApplication'
import * as clientApi from '../../../shared/api/client'
import '../../../shared/i18n'
import { useSession } from '../../auth/session'
import type { Utilisateur } from '../../auth/types'
import type { TransfertBackOffice } from './types'

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

const TRANSFERT_DEMANDE: TransfertBackOffice = {
  id: 6,
  reference: 'TRF-000006',
  sejour_reference: 'SEJ-000001',
  lieu_de_prise_en_charge: 'Aéroport FHB',
  commune: 'Cocody',
  type_vehicule_souhaite: 'Berline',
  date_heure_prevue: '10/11/2026 14:00',
  nombre_passagers: 2,
  nombre_bagages: 1,
  montant: 8000,
  etat: 'demande',
  etat_libelle: 'Demandé',
  chauffeur: null,
  vehicule: null,
  montant_verse_au_chauffeur: null,
  notes: null,
  code_prise_en_charge_emis: false,
  cree_le: '01/11/2026 09:00:00',
}

const TRANSFERT_AFFECTE: TransfertBackOffice = {
  ...TRANSFERT_DEMANDE,
  reference: 'TRF-000007',
  id: 7,
  etat: 'affecte',
  etat_libelle: 'Affecté',
  chauffeur: 'Yao Koffi',
  vehicule: 'CI-1234-AB',
  montant_verse_au_chauffeur: 5000,
  code_prise_en_charge_emis: true,
}

const TRANSFERT_ANNULE: TransfertBackOffice = { ...TRANSFERT_AFFECTE, etat: 'annule', etat_libelle: 'Annulé' }

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

describe('back office › transferts (CdC § 6.6)', () => {
  it('liste les transferts, ouvre le formulaire d’affectation d’un transfert demandé puis annule un transfert affecté', async () => {
    let annule = false
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/transferts') {
        return {
          elements: [TRANSFERT_DEMANDE, annule ? TRANSFERT_ANNULE : TRANSFERT_AFFECTE],
          pagination: { page: 1, par_page: 5, total: 2, derniere_page: 1 },
        } as never
      }
      if (url === '/backoffice/chauffeurs') {
        return {
          elements: [{ id: 1, actif: true, compte: { id: 5, nom: 'Koffi', prenoms: 'Yao', nom_complet: 'Yao Koffi', email: 'yao@exemple.ci', telephone: null, statut: 'actif' } }],
          pagination: { page: 1, par_page: 100, total: 1, derniere_page: 1 },
        } as never
      }
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockImplementation(async () => {
      annule = true
      return TRANSFERT_ANNULE as never
    })

    monter('/admin/transferts')

    expect(await screen.findByText('TRF-000006')).toBeInTheDocument()
    expect(screen.getByText('Demandé')).toBeInTheDocument()
    expect(screen.getByText('TRF-000007')).toBeInTheDocument()
    expect(screen.getByText('Affecté')).toBeInTheDocument()
    expect(screen.getByText('Yao Koffi')).toBeInTheDocument()

    // Un transfert « demandé » ouvre le formulaire d'affectation (chauffeur, véhicule, montant manuel).
    await userEvent.click(screen.getByText('TRF-000006'))
    expect(await screen.findByRole('combobox', { name: 'Chauffeur' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Véhicule' })).toBeInTheDocument()
    expect(screen.getByRole('spinbutton')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Annuler' }))

    // Un transfert « affecté » peut être annulé (confirmation explicite, jamais un window.confirm natif).
    const ligneAffectee = screen.getByText('TRF-000007').closest('tr')!
    await userEvent.click(within(ligneAffectee).getByRole('button', { name: 'Annuler le transfert' }))
    await userEvent.click(await screen.findByRole('button', { name: 'Confirmer' }))

    await waitFor(() => expect(envoyer).toHaveBeenCalledWith('/backoffice/transferts/7/annuler'))
    expect(await screen.findByText('Annulé')).toBeInTheDocument()
  })
})
