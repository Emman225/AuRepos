import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import { ErreurApi } from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
import type { LogementFiche } from '../site-public/types'
import type { Estimation, EtatPaiement, Sejour } from './types'

// jsdom n'a pas matchMedia, dont Ant Design a besoin.
Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

// jsdom n'a pas ResizeObserver, dont DatePicker a besoin.
class ResizeObserverSimule {
  observe() {}
  unobserve() {}
  disconnect() {}
}
vi.stubGlobal('ResizeObserver', ResizeObserverSimule)

const FICHE = {
  reference: 'LOG-00001',
  nom: 'Villa Riviera',
  lieu: { commune: 'Cocody', quartier: 'Riviera Palmeraie' },
} as LogementFiche

const ESTIMATION: Estimation = {
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
  disponible: true,
  fidelite: { solde: 500, utilisables: 500, valeur: 5000, valeur_du_point: 10, plafonne: false },
  code_promo: { valide: false, motif: null },
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
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[adresse]}>
        <RoutesApplication />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

async function saisirLesDates(): Promise<void> {
  const utilisateur = userEvent.setup({ delay: null })
  await utilisateur.type(screen.getByPlaceholderText('Arrivée'), '10/11/2026{Enter}')
  await utilisateur.type(screen.getByPlaceholderText('Départ'), '13/11/2026{Enter}')
}

beforeEach(() => {
  vi.restoreAllMocks()
  useSession.setState({ jeton: null, utilisateur: null, statut: 'anonyme' })
})

describe('tunnel de réservation (P1-PUB-05, P1-PUB-06)', () => {
  it('renvoie vers la connexion un visiteur non connecté', async () => {
    monter('/logements/LOG-00001/reserver')

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument()
  })

  it('recalcule le total par le serveur dès que les dates sont choisies, puis réserve', async () => {
    connecter()
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001/estimation') return ESTIMATION as never
      if (url === '/client/sejours')
        return {
          reference: 'SEJ-000001',
          net_a_payer: 109900,
          mode_reglement: 'en_ligne',
          expire_le: '23/09/2026 10:00:00',
        } as Sejour as never
      throw new Error(`URL inattendue : ${url}`)
    })
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001') return FICHE as never
      if (url === '/client/compte/a-terme') return { statut: 'aucune' } as never
      return { 'general.whatsapp': null } as never
    })
    monter('/logements/LOG-00001/reserver')

    await screen.findByText('Villa Riviera')
    await saisirLesDates()

    expect(await screen.findByText('109 900 F')).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith(
      '/catalogue/logements/LOG-00001/estimation',
      expect.objectContaining({ arrivee: '2026-11-10', depart: '2026-11-13' }),
    )

    await userEvent.click(screen.getByRole('button', { name: 'Réserver' }))

    expect(await screen.findByText('Réservation enregistrée')).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith(
      '/client/sejours',
      expect.objectContaining({ reference_logement: 'LOG-00001', mode_reglement: 'en_ligne' }),
    )
  })

  it('établit un devis sans réserver, puis le transforme en réservation d’un clic', async () => {
    connecter()
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001/estimation') return ESTIMATION as never
      if (url === '/client/devis') return { reference: 'DEV-000001' } as never
      if (url === '/client/devis/DEV-000001/transformation') {
        return {
          reference: 'SEJ-000009',
          net_a_payer: 109900,
          mode_reglement: 'agence',
          expire_le: null,
        } as Sejour as never
      }
      throw new Error(`URL inattendue : ${url}`)
    })
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001') return FICHE as never
      if (url === '/client/compte/a-terme') return { statut: 'aucune' } as never
      return { 'general.whatsapp': null } as never
    })
    monter('/logements/LOG-00001/reserver')

    await screen.findByText('Villa Riviera')
    await saisirLesDates()
    await screen.findByText('109 900 F')

    await userEvent.click(screen.getByRole('button', { name: 'Obtenir un devis' }))

    expect(await screen.findByText('Devis établi')).toBeInTheDocument()
    expect(screen.getByText(/DEV-000001/)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('radio', { name: 'Paiement en agence' }))
    await userEvent.click(screen.getByRole('button', { name: 'Transformer en réservation' }))

    expect(await screen.findByText('Réservation enregistrée')).toBeInTheDocument()
    expect(screen.getByText(/SEJ-000009/)).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith('/client/devis/DEV-000001/transformation', { mode_reglement: 'agence' })
  })

  it('ne propose le règlement à terme que si le compte est accepté', async () => {
    connecter()
    vi.spyOn(clientApi, 'envoyer').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001/estimation') return ESTIMATION as never
      throw new Error(`URL inattendue : ${url}`)
    })
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/catalogue/logements/LOG-00001') return FICHE as never
      if (url === '/client/compte/a-terme') return { statut: 'acceptee' } as never
      return { 'general.whatsapp': null } as never
    })
    monter('/logements/LOG-00001/reserver')

    await screen.findByText('Villa Riviera')
    await saisirLesDates()

    expect(await screen.findByLabelText('Sur mon compte à terme')).toBeInTheDocument()
  })
})

describe('retour de paiement (P1-PUB-07)', () => {
  it('affiche le montant réglé quand le paiement a réussi', async () => {
    connecter()
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/client/paiements/PAI-000001') {
        return {
          reference: 'PAI-000001',
          etat: 'reussi',
          montant: 109900,
          sejour: 'SEJ-000001',
          numero_recu: 'RC-000001',
          motif: null,
        } as EtatPaiement as never
      }
      return { 'general.whatsapp': null } as never
    })
    monter('/paiement/retour/PAI-000001')

    expect(await screen.findByText('Paiement confirmé')).toBeInTheDocument()
    expect(screen.getByText(/109 900 F/)).toBeInTheDocument()
  })

  it('affiche le motif d’échec, jamais le contenu brut du rappel', async () => {
    connecter()
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/client/paiements/PAI-000002') {
        return {
          reference: 'PAI-000002',
          etat: 'echoue',
          montant: 109900,
          sejour: 'SEJ-000001',
          numero_recu: null,
          motif: 'Solde insuffisant.',
        } as EtatPaiement as never
      }
      return { 'general.whatsapp': null } as never
    })
    monter('/paiement/retour/PAI-000002')

    expect(await screen.findByText('Le paiement n’a pas abouti')).toBeInTheDocument()
    expect(screen.getByText('Solde insuffisant.')).toBeInTheDocument()
  })

  it('affiche un message clair quand la vérification échoue, sans planter la page', async () => {
    connecter()
    vi.spyOn(clientApi, 'lire').mockRejectedValue(new ErreurApi('Introuvable', 404))
    monter('/paiement/retour/INEXISTANT')

    expect(await screen.findByText('Impossible de vérifier ce paiement.')).toBeInTheDocument()
  })
})
