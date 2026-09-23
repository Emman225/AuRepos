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
import type { DevisDeSejour } from '../../reservation/types'
import type { DevisBackOffice, ReservationBackOffice } from './types'

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

const DEVIS_DE_SEJOUR: DevisDeSejour = {
  nombre_de_nuits: 3,
  nuitees: [],
  hebergement_brut_ht: 90000,
  supplements: [],
  supplements_ht: 0,
  remise_pourcentage: 0,
  remise_ht: 0,
  reductions: [],
  reductions_ht: 0,
  reductions_plafonnees: false,
  hebergement_net_ht: 90000,
  extras: [],
  extras_ht: 0,
  transfert_ht: 0,
  total_ht: 90000,
  tva_hebergement_et_extras: 16200,
  tva_transfert: 0,
  total_tva: 16200,
  total_ttc: 106200,
  tdt: 2700,
  occupants_taxables: 2,
  taxe_de_sejour: 1000,
  autres_taxes: 3700,
  net_a_payer: 109900,
  caution: 50000,
  total_avec_caution: 159900,
  taux: { tva: 18, tva_transfert: 18, tdt: 3, taxe_sejour_montant: 500, taxe_sejour_base: 'occupant' },
}

const LOGEMENT = { id: 1, reference: 'LOG-00001', nom: 'Villa Riviera', residence: 'Riviera Palmeraie', residence_id: 1 }

const RESERVATION: ReservationBackOffice = {
  id: 42,
  reference: 'SEJ-000042',
  etat: 'demande',
  etat_libelle: 'Demandé',
  canal: 'telephone',
  client: { id: 5, nom: 'Awa Koné', email: 'awa@exemple.ci', telephone: '0707070707' },
  logement: LOGEMENT,
  arrivee: '2026-11-10',
  depart: '2026-11-13',
  nombre_de_nuits: 3,
  adultes: 2,
  enfants: 0,
  mode_reglement: 'agence',
  bon_de_commande: null,
  net_a_payer: 109900,
  caution: 50000,
  acompte_exige: 30000,
  reglement: { net_a_payer: 109900, encaisse: 0, encaisse_hors_avance: 0, en_cours: 0, reste_du: 109900, solde: false, acompte_atteint: false },
  expire_le: null,
  confirme_le: null,
  agent_accueil_id: null,
  obstacles_a_la_confirmation: ['L’acompte de 30 000 F n’est pas encaissé (0 F effectués).'],
  code_d_arrivee_emis: false,
  devis: DEVIS_DE_SEJOUR,
  arrive_le: null,
  parti_le: null,
  no_show_le: null,
  caution_retenue: null,
  caution_retenue_motif: null,
}

const DEVIS: DevisBackOffice = {
  id: 7,
  reference: 'DEV-000007',
  etat: 'en_attente',
  client: { id: 5, nom: 'Awa Koné', email: 'awa@exemple.ci', telephone: null },
  logement: LOGEMENT,
  arrivee: '2026-12-01',
  depart: '2026-12-03',
  adultes: 2,
  enfants: 0,
  net_a_payer: 60000,
  sejour: null,
  cree_le: '01/10/2026 10:00:00',
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

describe('réservations back office (P1-BO-02)', () => {
  it('liste les réservations en attente puis ouvre la fiche', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours') return { elements: [RESERVATION], pagination: { page: 1, par_page: 25, total: 1, derniere_page: 1 } } as never
      if (url === '/backoffice/sejours/42') return RESERVATION as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/reservations')

    expect(await screen.findByText('SEJ-000042')).toBeInTheDocument()
    await userEvent.click(screen.getByText('SEJ-000042'))

    expect(await screen.findByText('Villa Riviera')).toBeInTheDocument()
    expect(screen.getByText(/L’acompte de 30 000 F n’est pas encaissé/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Confirmer la réservation' })).toBeDisabled()
  })

  it('confirme une réservation sans obstacle', async () => {
    const reservationConfirmable: ReservationBackOffice = { ...RESERVATION, obstacles_a_la_confirmation: [] }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours/42') return reservationConfirmable as never
      if (url === '/backoffice/sejours/42/occupants') return [] as never
      if (url === '/backoffice/sejours/42/etats-des-lieux') return [] as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...reservationConfirmable, etat: 'confirme', etat_libelle: 'Confirmé' } as never)

    monter('/admin/reservations/42')

    const confirmer = await screen.findByRole('button', { name: 'Confirmer la réservation' })
    expect(confirmer).not.toBeDisabled()
    await userEvent.click(confirmer)

    expect(await screen.findByText('Confirmé')).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith('/backoffice/sejours/42/confirmation')
  })

  it('propose une réduction avec un motif', async () => {
    const reservationConfirmable: ReservationBackOffice = { ...RESERVATION, obstacles_a_la_confirmation: [] }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours/42') return reservationConfirmable as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ id: 1, statut: 'en_attente' } as never)

    monter('/admin/reservations/42')

    await userEvent.click(await screen.findByRole('button', { name: 'Proposer une réduction' }))
    await userEvent.clear(screen.getByRole('spinbutton'))
    await userEvent.type(screen.getByRole('spinbutton'), '15')
    await userEvent.type(screen.getByRole('textbox', { name: 'Motif' }), 'Geste commercial fidélité')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText(/Réduction proposée/)).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith('/backoffice/sejours/42/reduction', { pourcentage: 15, motif: 'Geste commercial fidélité' })
  })

  it('affiche l’onglet Devis', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours') return { elements: [], pagination: { page: 1, par_page: 25, total: 0, derniere_page: 1 } } as never
      if (url === '/backoffice/devis') return { elements: [DEVIS], pagination: { page: 1, par_page: 25, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/reservations')
    await screen.findByRole('button', { name: 'Réservation manuelle' })

    await userEvent.click(screen.getByText('Devis'))

    expect(await screen.findByText('DEV-000007')).toBeInTheDocument()
  })

  it('enregistre une réservation manuelle', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours') return { elements: [], pagination: { page: 1, par_page: 25, total: 0, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue(RESERVATION as never)

    monter('/admin/reservations')
    await userEvent.click(await screen.findByRole('button', { name: 'Réservation manuelle' }))

    await userEvent.type(screen.getByLabelText('Nom'), 'Koné')
    await userEvent.type(screen.getByLabelText('Courriel'), 'awa@exemple.ci')
    await userEvent.type(screen.getByLabelText('Logement (référence)'), 'LOG-00001')
    await userEvent.type(screen.getByPlaceholderText('Arrivée'), '10/11/2026{Enter}')
    await userEvent.type(screen.getByPlaceholderText('Départ'), '13/11/2026{Enter}')

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(envoyer).toHaveBeenCalledWith(
      '/backoffice/sejours',
      expect.objectContaining({ canal: 'telephone', reference_logement: 'LOG-00001', mode_reglement: 'agence' }),
    )
  })

  it('fait le check-in d’un séjour confirmé', async () => {
    const confirme: ReservationBackOffice = { ...RESERVATION, etat: 'confirme', etat_libelle: 'Confirmé', obstacles_a_la_confirmation: [] }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours/42') return confirme as never
      if (url === '/backoffice/sejours/42/occupants') return [] as never
      if (url === '/backoffice/sejours/42/etats-des-lieux') return [] as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...confirme, etat: 'arrive', etat_libelle: 'Arrivé' } as never)

    monter('/admin/reservations/42')

    await userEvent.click(await screen.findByRole('button', { name: 'Faire le check-in' }))
    const boiteDeDialogue = await screen.findByRole('dialog')
    await userEvent.type(within(boiteDeDialogue).getByLabelText('Code d’arrivée'), 'AB12CD')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Valider le check-in' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/sejours/42/check-in', { code: 'AB12CD' })
    expect(await screen.findByText('Arrivé')).toBeInTheDocument()
  })

  it('fait le check-out d’un séjour arrivé, avec caution retenue motivée', async () => {
    const arrive: ReservationBackOffice = { ...RESERVATION, etat: 'arrive', etat_libelle: 'Arrivé', obstacles_a_la_confirmation: [] }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours/42') return arrive as never
      if (url === '/backoffice/sejours/42/occupants') return [] as never
      if (url === '/backoffice/sejours/42/etats-des-lieux') return [] as never
      if (url === '/backoffice/sejours/42/consommations') return { hebergement: 109900, repas: 0, transferts: 0, total: 109900 } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...arrive, etat: 'parti', etat_libelle: 'Parti' } as never)

    monter('/admin/reservations/42')

    await userEvent.click(await screen.findByRole('button', { name: 'Faire le check-out' }))
    const boiteDeDialogue = await screen.findByRole('dialog')
    expect(await within(boiteDeDialogue).findByText('Hébergement')).toBeInTheDocument()
    await userEvent.type(within(boiteDeDialogue).getByRole('spinbutton'), '5000')
    await userEvent.type(within(boiteDeDialogue).getByRole('textbox', { name: 'Motif' }), 'Dégât constaté sur le mobilier')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Valider le check-out' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/sejours/42/check-out', { caution_retenue: 5000, motif: 'Dégât constaté sur le mobilier' })
    expect(await screen.findByText('Parti')).toBeInTheDocument()
  })

  it('prolonge un séjour arrivé', async () => {
    const arrive: ReservationBackOffice = { ...RESERVATION, etat: 'arrive', etat_libelle: 'Arrivé', obstacles_a_la_confirmation: [] }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/sejours/42') return arrive as never
      if (url === '/backoffice/sejours/42/occupants') return [] as never
      if (url === '/backoffice/sejours/42/etats-des-lieux') return [] as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...arrive, depart: '2026-11-15' } as never)

    monter('/admin/reservations/42')

    await userEvent.click(await screen.findByRole('button', { name: 'Prolonger / départ anticipé' }))
    const boiteDeDialogue = await screen.findByRole('dialog')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Enregistrer' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/sejours/42/depart', { depart: '2026-11-13' }, 'put')
  })
})
