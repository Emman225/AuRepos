import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../../routes/RoutesApplication'
import * as clientApi from '../../../shared/api/client'
import '../../../shared/i18n'
import { useSession } from '../../auth/session'
import type { Utilisateur } from '../../auth/types'
import type { Galerie, Logement, Residence, SituationPrix, SituationPublication } from './types'

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

const RESIDENCE: Residence = {
  id: 9,
  nom: 'Résidence Test',
  slug: 'residence-test',
  proprietaire: { id: 3, nom: 'ACME SARL', interne: false },
  lieu: { quartier_id: 1, quartier: 'Riviera', commune_id: 1, commune: 'Cocody', libelle: 'Cocody › Riviera' },
  adresse: null,
  repere: null,
  latitude: null,
  longitude: null,
  description: null,
  consignes_acces: null,
  mode_vente: 'logement',
  disponibilite: 'disponible',
  reouverture_prevue_le: null,
  active: true,
  nombre_logements: 1,
  logements: [],
}

const LOGEMENT: Logement = {
  id: 21,
  residence_id: 9,
  reference: 'LOG-00021',
  nom: 'Studio A',
  type: { id: 1, code: 'studio', nom: 'Studio' },
  resume: 'Studio, 0 chambre',
  nombre_pieces: 1,
  nombre_chambres: 0,
  nombre_lits: 1,
  nombre_salles_de_bain: 1,
  capacite_de_base: 1,
  capacite_maximale: 2,
  surface_m2: null,
  description: null,
  regles: { fumeur_autorise: false, animaux_autorises: false, fetes_autorisees: false, texte: null },
  heure_arrivee: null,
  heure_depart: null,
  caution: 20000,
  duree_minimale: null,
  duree_maximale: null,
  politique_annulation: 'moderee',
  politique_annulation_libelle: 'Modérée',
  prix_proprietaire: null,
  prix_vente: null,
  marge_par_nuitee: null,
  etat_publication: 'brouillon',
  etat_publication_libelle: 'Brouillon',
  mise_en_avant: false,
}

const SITUATION_PRIX: SituationPrix = {
  prix_proprietaire: null,
  prix_vente: null,
  marge_par_nuitee: null,
  pourcentage_entreprise: 30,
  pourcentage_entreprise_derogation: null,
  prix_de_vente_conseille: null,
  marge_conseillee_respectee: null,
  controle_mediane: null,
  changement_en_attente: null,
  derogation_en_attente: null,
  negociation: [],
}

const SITUATION_PUBLICATION: SituationPublication = {
  etat: 'brouillon',
  etat_libelle: 'Brouillon',
  motif: null,
  obstacles: ['Il n’y a pas assez de photos (0 sur 5 minimum).'],
  controle_mediane: null,
}

const GALERIE_VIDE: Galerie = { photos: [], nombre: 0, minimum: 5, maximum: 30, assez_pour_publier: false }

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

describe('catalogue back office (P1-BO-03)', () => {
  it('liste les résidences, ouvre le formulaire de création et choisit le propriétaire', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/residences') return { elements: [RESIDENCE], pagination: { page: 1, par_page: 25, total: 1, derniere_page: 1 } } as never
      if (url === '/backoffice/proprietaires') return { elements: [{ id: 3, nom_affiche: 'ACME SARL' }] } as never
      if (url === '/referentiels/communes') return [{ id: 1, nom: 'Cocody' }] as never
      if (url === '/referentiels/quartiers') return [{ id: 1, nom: 'Riviera', commune_id: 1 }] as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/catalogue')

    expect(await screen.findByText('Résidence Test')).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: 'Nouvelle résidence' }))
    await userEvent.type(screen.getByLabelText('Nom'), 'Nouvelle résidence')

    await waitFor(() => expect(clientApi.lire).toHaveBeenCalledWith('/backoffice/proprietaires', expect.anything()))
    const proprietaireCombobox = await screen.findByRole('combobox', { name: 'Propriétaire' })
    await userEvent.click(proprietaireCombobox)
    await userEvent.click(await screen.findByRole('option', { name: 'ACME SARL' }))

    // Le quartier (obligatoire) n'est pas choisi : la validation cliente doit le signaler,
    // pas envoyer une création incomplète. Le reste du circuit (choix en cascade
    // commune -> quartier, déjà éprouvé dans FormulaireRecherche) est vérifié en direct.
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(await screen.findByText('Choisissez le quartier.')).toBeInTheDocument()
  })

  it('affiche la fiche d’une résidence avec ses logements', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/residences/9') return { ...RESIDENCE, logements: [LOGEMENT] } as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/catalogue/9')

    expect(await screen.findByText('LOG-00021')).toBeInTheDocument()
    expect(screen.getByText('Studio A')).toBeInTheDocument()
  })

  it('affiche la fiche d’un logement en brouillon : obstacles affichés, « Soumettre » reste actionnable (le serveur tranche)', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/residences/9/logements/21') return LOGEMENT as never
      if (url === '/backoffice/residences/9/logements/21/prix') return SITUATION_PRIX as never
      if (url === '/backoffice/residences/9/logements/21/publication') return SITUATION_PUBLICATION as never
      if (url === '/backoffice/residences/9/logements/21/photos') return GALERIE_VIDE as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/catalogue/9/logements/21')

    expect(await screen.findByText('LOG-00021 — Studio A')).toBeInTheDocument()
    expect(screen.getByText(/Il n’y a pas assez de photos/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Soumettre à validation' })).not.toBeDisabled()
  })

  it('désactive « Publier » tant que des obstacles bloquent la publication (état en attente)', async () => {
    const enAttente = { ...LOGEMENT, etat_publication: 'en_attente' as const, etat_publication_libelle: 'En attente de validation' }
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/residences/9/logements/21') return enAttente as never
      if (url === '/backoffice/residences/9/logements/21/prix') return SITUATION_PRIX as never
      if (url === '/backoffice/residences/9/logements/21/publication') return SITUATION_PUBLICATION as never
      if (url === '/backoffice/residences/9/logements/21/photos') return GALERIE_VIDE as never
      return { 'general.whatsapp': null } as never
    })

    monter('/admin/catalogue/9/logements/21')

    expect(await screen.findByRole('button', { name: 'Publier' })).toBeDisabled()
  })

  it('propose un prix de vente depuis la fiche du logement', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/residences/9/logements/21') return LOGEMENT as never
      if (url === '/backoffice/residences/9/logements/21/prix') return SITUATION_PRIX as never
      if (url === '/backoffice/residences/9/logements/21/publication') return SITUATION_PUBLICATION as never
      if (url === '/backoffice/residences/9/logements/21/photos') return GALERIE_VIDE as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ id: 1, statut: 'en_attente' } as never)

    monter('/admin/catalogue/9/logements/21')
    await screen.findByText('LOG-00021 — Studio A')

    const montants = screen.getAllByRole('spinbutton')
    await userEvent.type(montants[1], '25000')
    await userEvent.click(screen.getAllByRole('button', { name: 'Proposer' })[1])

    expect(envoyer).toHaveBeenCalledWith('/backoffice/residences/9/logements/21/prix/vente', { montant: 25000, motif: undefined })
  })
})
