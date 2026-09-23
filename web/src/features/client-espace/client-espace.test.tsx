import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
import type { Devis, Sejour } from '../reservation/types'
import '../../shared/i18n'
import type { MesPaiements, MonCompte, CompteATermeDetail } from './types'

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

const LOGEMENT = { reference: 'LOG-00001', nom: 'Villa Riviera', resume: '', residence: 'Riviera', lieu: { commune: 'Cocody', quartier: 'Riviera Palmeraie' } }

const SEJOUR: Sejour = {
  reference: 'SEJ-000001',
  etat: 'demande',
  etat_libelle: 'Demandé',
  arrivee: '2026-11-10',
  depart: '2026-11-13',
  nombre_de_nuits: 3,
  adultes: 2,
  enfants: 0,
  logement: LOGEMENT,
  code_d_arrivee: null,
  acces: null,
  mode_reglement: 'agence',
  bon_de_commande: null,
  devis: {
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
  },
  net_a_payer: 109900,
  caution: 50000,
  acompte_exige: 0,
  points_utilises: 0,
  reduction_points: 0,
  expire_le: null,
  annulation: {
    politique: 'standard',
    politique_libelle: 'Standard',
    gratuite_jusqu_au: '08/11/2026',
    pourcentage_retenu_ensuite: 50,
    retenu_si_annule_maintenant: 0,
    annule_le: null,
    motif: null,
    montant_retenu: null,
  },
}

const DEVIS: Devis = {
  reference: 'DEV-000001',
  etat: 'en_attente',
  etat_libelle: 'En attente',
  arrivee: '2026-11-10',
  depart: '2026-11-13',
  nombre_de_nuits: 3,
  adultes: 2,
  enfants: 0,
  logement: LOGEMENT,
  code_promo: null,
  devis: SEJOUR.devis!,
  net_a_payer: 109900,
  caution: 50000,
  points_utilises: 0,
  reduction_points: 0,
  sejour: null,
  cree_le: '01/10/2026 10:00:00',
}

const client = (surcharge: Partial<Utilisateur> = {}): Utilisateur => ({
  id: 1,
  nom: 'Koné',
  prenoms: 'Awa',
  nom_complet: 'Awa Koné',
  email: 'awa@exemple.ci',
  telephone: null,
  profil: 'client',
  profil_libelle: 'Client',
  espace: 'client',
  agence: null,
  peut_encaisser: false,
  ...surcharge,
})

function connecter(): void {
  useSession.setState({ jeton: 'jeton-test', utilisateur: client(), statut: 'connecte' })
}

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
  connecter()
})

describe('mes séjours (P1-CLI-01)', () => {
  it('liste mes séjours puis affiche le détail, avec annulation d’une simple demande', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/client/sejours') return { elements: [SEJOUR], pagination: { page: 1, par_page: 20, total: 1, derniere_page: 1 } } as never
      if (url === '/client/sejours/SEJ-000001') return SEJOUR as never
      if (url === '/client/restaurateurs') return [] as never
      if (url === '/client/sejours/SEJ-000001/commandes') return [] as never
      if (url === '/client/sejours/SEJ-000001/transferts') return [] as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...SEJOUR, etat: 'annule', etat_libelle: 'Annulé' } as never)

    monter('/mon-espace/sejours')
    expect(await screen.findByText('SEJ-000001')).toBeInTheDocument()

    await userEvent.click(screen.getByText('SEJ-000001'))
    expect(await screen.findByText('Villa Riviera')).toBeInTheDocument()
    expect(screen.getByText('Annuler ce séjour')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Annuler ce séjour'))
    await userEvent.click(await screen.findByRole('button', { name: 'Confirmer' }))

    expect(envoyer).toHaveBeenCalledWith('/client/sejours/SEJ-000001/annulation')
  })
})

describe('mes devis (P1-CLI-01)', () => {
  it('liste mes devis puis archive un devis en attente', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/client/devis') return { elements: [DEVIS], pagination: { page: 1, par_page: 20, total: 1, derniere_page: 1 } } as never
      if (url === '/client/devis/DEV-000001') return DEVIS as never
      if (url === '/client/compte/a-terme') return { statut: 'aucune' } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...DEVIS, etat: 'archive', etat_libelle: 'Archivé' } as never)

    monter('/mon-espace/devis')
    expect(await screen.findByText('DEV-000001')).toBeInTheDocument()

    await userEvent.click(screen.getByText('DEV-000001'))
    expect(await screen.findByText('Villa Riviera')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Archiver ce devis'))
    await userEvent.click(await screen.findByRole('button', { name: 'Confirmer' }))

    expect(envoyer).toHaveBeenCalledWith('/client/devis/DEV-000001', undefined, 'delete')
  })
})

describe('mes paiements (P1-CLI-02)', () => {
  it('affiche l’avance disponible, le reste à régler en agence et mes règlements', async () => {
    const paiements: MesPaiements = {
      avance_disponible: 20000,
      montant_a_regler_en_agence: 70000,
      fidelite: { solde: 120, valeur: 1200, valeur_du_point: 10, montant_par_point: 500, mouvements: [] },
      reglements: [
        { reference: 'REG-000001', numero_recu: 'RC-2026-001', date: '01/10/2026', montant: 30000, mode: 'Espèces', affaires: ['SEJ-000001'] },
      ],
    }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/client/paiements') return paiements as never
      return { 'general.whatsapp': null } as never
    })

    monter('/mon-espace/paiements')

    expect(await screen.findByText('70 000 F')).toBeInTheDocument()
    expect(screen.getByText('20 000 F')).toBeInTheDocument()
    expect(screen.getByText('RC-2026-001')).toBeInTheDocument()
  })
})

describe('mon compte (P1-CLI-03)', () => {
  it('affiche mes coordonnées, mon régime et propose la demande de compte à terme', async () => {
    const compte: MonCompte = {
      nom: 'Koné', prenoms: 'Awa', nom_complet: 'Awa Koné', email: 'awa@exemple.ci', telephone: '0707070707',
      nature: 'b2c', nature_libelle: 'Particulier', raison_sociale: null, ncc: null,
      tva_hebergement: true, tva_transfert: true,
    }
    const dossier: CompteATermeDetail = {
      client_id: 1, statut: 'aucune', statut_libelle: 'Aucune demande', nature: 'b2c', nature_libelle: 'Particulier',
      raison_sociale: null, plafond_credit: 0, encours: null, demande_le: null, motif_refus: null, traite_le: null,
    }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/client/compte') return compte as never
      if (url === '/client/compte/a-terme') return dossier as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...dossier, statut: 'en_attente', statut_libelle: 'En attente' } as never)

    monter('/mon-espace/compte')

    expect(await screen.findByText('Particulier')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Demander un compte à terme'))
    expect(envoyer).toHaveBeenCalledWith('/client/compte/a-terme/demande')
  })
})
