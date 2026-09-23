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
import type { DonneesParametres } from '../parametres/types'
import type { ChangementAValider, Reclamation } from './types'

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

const RECLAMATION: Reclamation = {
  id: 9,
  sejour: { reference: 'SEJ-000030', logement: 'Villa Riviera' },
  client: 'Awa Koné',
  motif: 'Le ménage promis avant l’arrivée n’a pas été fait.',
  statut: 'ouverte',
  statut_libelle: 'Ouverte',
  reponse: null,
  avoir_montant: null,
  avoir_motif: null,
  fermee_le: null,
  created_at: '10/11/2026 09:00:00',
}

const PARAMETRES_AVEC_TRESORIER: DonneesParametres = {
  onglets: [
    {
      code: 'gestionnaires',
      libelle: 'Gestionnaires',
      parametres: [
        {
          cle: 'gestionnaires.validant_2_id',
          nom: 'validant_2_id',
          libelle: 'Trésorier désigné',
          type: 'administrateur',
          valeur: 3,
          defaut: null,
          options: null,
          aide: null,
          reserve_super_administrateur: true,
          double_validation: false,
        },
      ],
    },
  ],
  administrateurs: [],
  alertes: [],
}

const PARAMETRES_SANS_TRESORIER: DonneesParametres = {
  onglets: [{ ...PARAMETRES_AVEC_TRESORIER.onglets[0], parametres: [{ ...PARAMETRES_AVEC_TRESORIER.onglets[0].parametres[0], valeur: null }] }],
  administrateurs: [],
  alertes: [],
}

const CHANGEMENT_AVOIR_EN_ATTENTE: ChangementAValider = {
  id: 5,
  sujet: 'Réclamation SEJ-000030',
  sujet_type: 'reclamation',
  sujet_id: 9,
  champ: 'avoir_montant',
  valeur_actuelle: null,
  valeur_proposee: '10000',
  motif: 'Geste commercial',
  statut: 'en_attente',
  propose_par: 'Moussa Diallo',
  propose_le: '10/11/2026 10:00:00',
  decide_par: null,
  decide_le: null,
  motif_decision: null,
  je_peux_valider: true,
  je_peux_annuler: false,
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

describe('back office › réclamations (P2-AST-01)', () => {
  it('désactive « Proposer un avoir » sans trésorier désigné', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/reclamations') return { elements: [RECLAMATION], pagination: { page: 1, par_page: 5, total: 1, derniere_page: 1 } } as never
      if (url === '/backoffice/changements') return { elements: [], pagination: { page: 1, par_page: 100, total: 0, derniere_page: 1 } } as never
      if (url === '/backoffice/parametres') return PARAMETRES_SANS_TRESORIER as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/reclamations')

    expect(await screen.findByText('Le ménage promis avant l’arrivée n’a pas été fait.')).toBeInTheDocument()
    expect(await screen.findByRole('button', { name: 'Proposer un avoir' })).toBeDisabled()
  })

  it(
    'propose un avoir puis le trésorier le confirme depuis la file des changements',
    async () => {
    let enAttente = false
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/reclamations') return { elements: [RECLAMATION], pagination: { page: 1, par_page: 5, total: 1, derniere_page: 1 } } as never
      if (url === '/backoffice/changements') {
        return {
          elements: enAttente ? [CHANGEMENT_AVOIR_EN_ATTENTE] : [],
          pagination: { page: 1, par_page: 100, total: enAttente ? 1 : 0, derniere_page: 1 },
        } as never
      }
      if (url === '/backoffice/parametres') return PARAMETRES_AVEC_TRESORIER as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockImplementation(async (url: string) => {
      if (url === '/backoffice/reclamations/9/avoir') {
        enAttente = true
        return { ...RECLAMATION, statut: 'en_cours' } as never
      }
      return { ...CHANGEMENT_AVOIR_EN_ATTENTE, statut: 'valide' } as never
    })

    monter('/admin/reclamations')

    const proposer = await screen.findByRole('button', { name: 'Proposer un avoir' })
    expect(proposer).not.toBeDisabled()
    await userEvent.click(proposer)

    const dialogueAvoir = await screen.findByRole('dialog')
    await userEvent.type(within(dialogueAvoir).getByRole('spinbutton'), '10000')
    await userEvent.type(within(dialogueAvoir).getByRole('textbox', { name: 'Motif' }), 'Geste commercial fidélité')
    await userEvent.click(within(dialogueAvoir).getByRole('button', { name: 'Proposer un avoir' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/reclamations/9/avoir', { montant: 10000, motif: 'Geste commercial fidélité' })
    expect(await screen.findByText('Proposé, en attente de confirmation')).toBeInTheDocument()

    // La modale précédente peut rester montée le temps de son animation de fermeture (jsdom) :
    // on prend la DERNIÈRE boîte de dialogue apparue plutôt qu'une requête qui suppose qu'une
    // seule existe, un éventuel reliquat de l'ancienne restant parfois affiché un instant.
    await userEvent.click(screen.getByRole('button', { name: 'Confirmer' }))
    const dialoguesOuverts = await waitFor(() => {
      const trouvees = screen.getAllByRole('dialog')
      expect(trouvees.length).toBeGreaterThan(0)
      return trouvees
    })
    const dialogueDecision = dialoguesOuverts[dialoguesOuverts.length - 1]
    await userEvent.click(within(dialogueDecision).getByRole('combobox', { name: 'Mode de remboursement' }))
    await userEvent.click(await screen.findByText('Espèces'))
    await userEvent.click(within(dialogueDecision).getByRole('button', { name: 'Confirmer' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/changements/5/decision', { decision: 'valider', motif: undefined, mode_de_remboursement: 'especes' }, 'put')
    },
    45000,
  )
})
