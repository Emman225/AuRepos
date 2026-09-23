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
import type { Mission, SejourAgent } from './types'

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

const LOGEMENT = { id: 1, reference: 'LOG-00001', nom: 'Villa Riviera', residence: 'Riviera Palmeraie', residence_id: 1 }

const SEJOUR_CONFIRME: SejourAgent = {
  id: 101,
  reference: 'SEJ-000101',
  etat: 'confirme',
  etat_libelle: 'Confirmé',
  canal: 'direct',
  client: { id: 5, nom: 'Awa Koné', email: 'awa@exemple.ci', telephone: '0707070707' },
  logement: LOGEMENT,
  arrivee: '2026-11-10',
  depart: '2026-11-13',
  nombre_de_nuits: 3,
  adultes: 2,
  enfants: 0,
  mode_reglement: 'en_ligne',
  bon_de_commande: null,
  net_a_payer: 109900,
  caution: 50000,
  acompte_exige: 30000,
  reglement: { net_a_payer: 109900, encaisse: 109900, encaisse_hors_avance: 79900, en_cours: 0, reste_du: 0, solde: true, acompte_atteint: true },
  expire_le: null,
  confirme_le: '01/11/2026 10:00:00',
  agent_accueil_id: null,
  obstacles_a_la_confirmation: [],
  code_d_arrivee_emis: true,
  devis: null,
  arrive_le: null,
  parti_le: null,
  no_show_le: null,
  caution_retenue: null,
  caution_retenue_motif: null,
}

const SEJOUR_ARRIVE: SejourAgent = { ...SEJOUR_CONFIRME, id: 102, reference: 'SEJ-000102', etat: 'arrive', etat_libelle: 'Arrivé' }

const MISSION_A_FAIRE: Mission = {
  id: 7,
  type: 'apres_depart',
  type_libelle: 'Après départ',
  origine: 'automatique',
  origine_libelle: 'Automatique',
  statut: 'a_faire',
  statut_libelle: 'À faire',
  logement: { id: 4, nom: 'Villa Riviera', residence: 'Riviera Palmeraie' },
  sejour: { reference: 'SEJ-000010', arrivee: '2026-11-01', depart: '2026-11-05' },
  agent: 'Fatou Traoré',
  echeance: '2026-11-05 12:00:00',
  notes: null,
  debutee_le: null,
  terminee_le: null,
  created_at: '01/11/2026 08:00:00',
}

const agentTerrain = (): Utilisateur => ({
  id: 3,
  nom: 'Traoré',
  prenoms: 'Fatou',
  nom_complet: 'Fatou Traoré',
  email: 'fatou@exemple.ci',
  telephone: null,
  profil: 'agent_terrain',
  profil_libelle: 'Agent de terrain',
  espace: 'agent',
  agence: null,
  peut_encaisser: false,
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
  useSession.setState({ jeton: 'jeton-test', utilisateur: agentTerrain(), statut: 'connecte' })
})

describe('espace agent de terrain (CdC § 6.3, P2-MOB-04)', () => {
  it('affiche le tableau de bord puis navigue vers mes séjours et mes missions', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/agent/sejours') return [SEJOUR_CONFIRME, SEJOUR_ARRIVE] as never
      if (url === '/agent/missions') return [MISSION_A_FAIRE] as never
      return { 'general.whatsapp': null } as never
    })

    monter('/agent')

    expect(await screen.findByText('Bonjour Fatou')).toBeInTheDocument()
    expect(await screen.findByText('Arrivées à accueillir aujourd’hui')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes séjours du jour'))
    expect(await screen.findByText('SEJ-000101')).toBeInTheDocument()
    expect(screen.getByText('SEJ-000102')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes missions'))
    expect(await screen.findByText('Après départ')).toBeInTheDocument()
  })

  it('fait le check-in d’un séjour confirmé sans jamais afficher le code', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/agent/sejours/101') return SEJOUR_CONFIRME as never
      if (url === '/agent/sejours/101/occupants') return [] as never
      if (url === '/agent/sejours/101/etats-des-lieux') return [] as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi
      .spyOn(clientApi, 'envoyer')
      .mockResolvedValue({ ...SEJOUR_CONFIRME, etat: 'arrive', etat_libelle: 'Arrivé' } as never)

    monter('/agent/sejours/101')

    expect(await screen.findByText('Villa Riviera')).toBeInTheDocument()
    await userEvent.click(screen.getByRole('button', { name: 'Faire le check-in' }))

    const boiteDeDialogue = await screen.findByRole('dialog')
    await userEvent.type(within(boiteDeDialogue).getByLabelText('Code d’arrivée'), 'AB12CD')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Valider le check-in' }))

    expect(envoyer).toHaveBeenCalledWith('/agent/sejours/101/check-in', { code: 'AB12CD' })
    expect(await screen.findByText('Arrivé')).toBeInTheDocument()
  })

  it('fait le check-out d’un séjour arrivé, avec caution retenue motivée', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/agent/sejours/102') return SEJOUR_ARRIVE as never
      if (url === '/agent/sejours/102/occupants') return [] as never
      if (url === '/agent/sejours/102/etats-des-lieux') return [] as never
      if (url === '/agent/sejours/102/consommations') return { hebergement: 109900, repas: 0, transferts: 0, total: 109900 } as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi
      .spyOn(clientApi, 'envoyer')
      .mockResolvedValue({ ...SEJOUR_ARRIVE, etat: 'parti', etat_libelle: 'Parti' } as never)

    monter('/agent/sejours/102')

    await userEvent.click(await screen.findByRole('button', { name: 'Faire le check-out' }))
    const boiteDeDialogue = await screen.findByRole('dialog')
    expect(await within(boiteDeDialogue).findByText('Hébergement')).toBeInTheDocument()
    await userEvent.type(within(boiteDeDialogue).getByRole('spinbutton'), '5000')
    await userEvent.type(within(boiteDeDialogue).getByRole('textbox', { name: 'Motif' }), 'Dégât constaté sur le mobilier')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Valider le check-out' }))

    expect(envoyer).toHaveBeenCalledWith('/agent/sejours/102/check-out', { caution_retenue: 5000, motif: 'Dégât constaté sur le mobilier' })
    expect(await screen.findByText('Parti')).toBeInTheDocument()
  })

  it('démarre puis termine une mission qui lui est affectée', async () => {
    const missionEnCours: Mission = { ...MISSION_A_FAIRE, statut: 'en_cours', statut_libelle: 'En cours' }
    let appels = 0
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/agent/missions') {
        appels += 1
        return [appels === 1 ? MISSION_A_FAIRE : missionEnCours] as never
      }
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue(missionEnCours as never)

    monter('/agent/missions')

    await userEvent.click(await screen.findByRole('button', { name: 'Démarrer' }))
    expect(envoyer).toHaveBeenCalledWith('/agent/missions/7/debut')

    await userEvent.click(await screen.findByRole('button', { name: 'Terminer' }))
    const boiteDeDialogue = await screen.findByRole('dialog')
    await userEvent.click(within(boiteDeDialogue).getByRole('button', { name: 'Terminer' }))

    expect(envoyer).toHaveBeenCalledWith('/agent/missions/7/fin', { notes: undefined })
  })
})
