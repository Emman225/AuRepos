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
import type { CodePromo, Derogation, PrixNegocie } from './types'

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

const TYPES_LOGEMENT = [{ id: 3, nom: 'F3' }]

const DEROGATIONS: Derogation[] = [
  { logement_id: 9, reference: 'LOG-00009', nom: 'Villa Riviera', residence: 'Riviera Palmeraie', taux_global: 20, taux_derogation: null },
]

const PRIX_NEGOCIE: PrixNegocie = {
  id: 1,
  client: { id: 5, nom: 'Awa Koné', email: 'awa@exemple.ci' },
  type_logement: { id: 3, nom: 'F3' },
  tarif_par_nuit: 20000,
  actif: true,
  notes: null,
  modifie_le: '22/09/2026 10:00:00',
}

const CODE_PROMO: CodePromo = {
  id: 1,
  code: 'NOEL2026',
  type: 'pourcentage',
  valeur: 15,
  date_debut: '01/12/2026',
  date_fin: '31/12/2026',
  residence: null,
  actif: true,
  description: null,
  valable_aujourd_hui: false,
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

describe('tarification back office (P1-BO-05)', () => {
  // Arrivée depuis la fiche d'un logement (?logement_id=9, cf. PageDetailLogement) : ce
  // chemin évite le sélecteur de type de logement, dont le clic d'option est — comme déjà
  // constaté pour d'autres écrans (FormulaireProprietaire) — peu fiable sous jsdom ; il est
  // vérifié en conditions réelles, navigateur.
  it('affiche la grille tarifaire propre à un logement (arrivée via ?logement_id)', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/referentiels/types-logement') return TYPES_LOGEMENT as never
      if (url === '/backoffice/tarification/verification') return { muette: true, nombre_bloquantes: 0, anomalies: [] } as never
      if (url === '/backoffice/tarification/grille') {
        return {
          cible: { logement_id: 9 },
          tranches: [{ id: 1, nom: '1-3 nuits', nuits_min: 1, nuits_max: 3 }],
          saisons: [{ id: 1, nom: 'Saison sèche', categorie: 'basse', date_debut: '01/01/2026', date_fin: '31/03/2026', tarifs: { 1: 30000 } }],
        } as never
      }
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/tarification?logement_id=9')

    expect(await screen.findByText('Saison sèche')).toBeInTheDocument()
    expect(screen.getByText('Grille tarifaire propre à ce logement.')).toBeInTheDocument()
  })

  it('propose un pourcentage entreprise global et liste les dérogations', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/referentiels/types-logement') return TYPES_LOGEMENT as never
      if (url === '/backoffice/tarification/verification') return { muette: true, nombre_bloquantes: 0, anomalies: [] } as never
      if (url === '/backoffice/tarification/derogations') return { derogations: DEROGATIONS } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({
      id: 1,
      sujet: null,
      sujet_type: null,
      sujet_id: null,
      champ: 'pourcentage_entreprise',
      valeur_actuelle: '20',
      valeur_proposee: '25',
      motif: null,
      statut: 'en_attente',
      propose_par: 'Moussa Diallo',
      propose_le: '22/09/2026 10:00:00',
      decide_par: null,
      decide_le: null,
      motif_decision: null,
      je_peux_valider: false,
      je_peux_annuler: true,
    } as never)

    monter('/admin/tarification')
    await screen.findByRole('heading', { name: 'Tarification' })
    await userEvent.click(screen.getByText('Pourcentage entreprise'))

    expect(await screen.findByText('Villa Riviera')).toBeInTheDocument()

    const [saisie] = await screen.findAllByRole('spinbutton')
    await userEvent.type(saisie, '25')
    await userEvent.click(screen.getByRole('button', { name: 'Proposer' }))

    expect(await screen.findByText(/Proposition envoyée/)).toBeInTheDocument()
    expect(envoyer).toHaveBeenCalledWith('/backoffice/tarification/pourcentage-entreprise', { taux: 25, motif: undefined })
  })

  it('liste les prix négociés et ouvre le formulaire de création', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/referentiels/types-logement') return TYPES_LOGEMENT as never
      if (url === '/backoffice/tarification/verification') return { muette: true, nombre_bloquantes: 0, anomalies: [] } as never
      if (url === '/backoffice/prix-negocies') return { elements: [PRIX_NEGOCIE], pagination: { page: 1, par_page: 100, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/tarification')
    await screen.findByRole('heading', { name: 'Tarification' })
    await userEvent.click(screen.getByText('Prix négociés'))

    expect(await screen.findByText('Awa Koné')).toBeInTheDocument()
    expect(screen.getByText('20 000 F')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Nouveau prix négocié' }))
    expect(await screen.findByRole('button', { name: 'Enregistrer' })).toBeDisabled()
  })

  it('liste les codes promo', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/referentiels/types-logement') return TYPES_LOGEMENT as never
      if (url === '/backoffice/tarification/verification') return { muette: true, nombre_bloquantes: 0, anomalies: [] } as never
      if (url === '/backoffice/codes-promo') return { elements: [CODE_PROMO], pagination: { page: 1, par_page: 100, total: 1, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/tarification')
    await screen.findByRole('heading', { name: 'Tarification' })
    await userEvent.click(screen.getByText('Codes promo'))

    expect(await screen.findByText('NOEL2026')).toBeInTheDocument()
    expect(screen.getByText('15 %')).toBeInTheDocument()
  })

  it('liste les saisons dans l’onglet référentiels', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/referentiels/types-logement') return TYPES_LOGEMENT as never
      if (url === '/backoffice/tarification/verification') return { muette: true, nombre_bloquantes: 0, anomalies: [] } as never
      if (url === '/backoffice/referentiels/saisons') {
        return {
          elements: [{ id: 1, nom: 'Saison des pluies', categorie: 'basse', date_debut: '2026-01-01', date_fin: '2026-03-31', actif: true }],
          pagination: { page: 1, par_page: 100, total: 1, derniere_page: 1 },
        } as never
      }
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/tarification')
    await screen.findByRole('heading', { name: 'Tarification' })
    await userEvent.click(screen.getByText('Saisons, tranches et suppléments'))

    expect(await screen.findByText('Saison des pluies')).toBeInTheDocument()
    expect(screen.getByText(/supprimer cette saison ou cette tranche/)).toBeInTheDocument()
  })
})
