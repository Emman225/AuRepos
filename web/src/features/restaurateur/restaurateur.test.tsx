import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { App as AppAntd } from 'antd'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
import type { Commande, CompteursRestaurateur, DetteRestaurateur, ProduitRepas } from './types'

Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

const restaurateur = (): Utilisateur => ({
  id: 1,
  nom: 'Kouassi',
  prenoms: 'Awa',
  nom_complet: 'Awa Kouassi',
  email: 'awa@exemple.ci',
  telephone: null,
  profil: 'restaurateur',
  profil_libelle: 'Restaurateur',
  espace: 'restaurateur',
  agence: null,
  peut_encaisser: false,
})

const COMPTEURS: CompteursRestaurateur = {
  commandes_par_etat: { demande: 1, confirmee: 2, en_preparation: 0, prete: 0, en_livraison: 0, livree: 0, annulee: 0, refusee: 0 },
  dette: { du: 5000, deja_verse: 1000 },
}

const PRODUITS: ProduitRepas[] = [
  { id: 1, restaurateur_id: 1, nom: 'Riz gras', description: null, categorie: 'plat', prix_restaurateur: 2000, prix_vente: 2400, disponible: true },
]

const COMMANDES: Commande[] = [
  {
    id: 1,
    reference: 'CR-000001',
    sejour_id: 1,
    sejour_reference: 'SEJ-000001',
    restaurateur_id: 1,
    restaurateur: 'Chez Awa',
    etat: 'confirmee',
    etat_libelle: 'Confirmée',
    mode_reglement: 'note_du_sejour',
    montant_total: 4800,
    livreur_id: null,
    livreur: null,
    remuneration_livreur: null,
    code_livraison_emis: false,
    notes: null,
    lignes: [{ id: 10, produit_id: 1, nom_produit: 'Riz gras', prix_unitaire_vente: 2400, quantite_commandee: 2, quantite_servie: null, montant: 4800 }],
    cree_le: null,
  },
]

const DETTE: DetteRestaurateur = { du: 5000, deja_verse: 1000, paiements: [{ reference: 'REG-000001', montant: 1000, mode: 'Espèces', date: '20/09/2026' }] }

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
  useSession.setState({ jeton: 'jeton-test', utilisateur: restaurateur(), statut: 'connecte' })
})

describe('espace restaurateur', () => {
  it('affiche le tableau de bord puis navigue vers la carte, les commandes et la dette', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/restaurateur/tableau-de-bord') return COMPTEURS as never
      if (url === '/restaurateur/produits') return PRODUITS as never
      if (url === '/restaurateur/commandes') return COMMANDES as never
      if (url === '/restaurateur/dette') return DETTE as never
      return { 'general.whatsapp': null } as never
    })

    monter('/restaurateur')

    expect(await screen.findByText('Bonjour Awa')).toBeInTheDocument()
    expect(await screen.findByText('5 000 F')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Ma carte'))
    expect(await screen.findByText('Riz gras')).toBeInTheDocument()
    expect(screen.getByText('2 400 F')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes commandes'))
    expect(await screen.findByText('CR-000001')).toBeInTheDocument()
    expect(screen.getByText('Démarrer la préparation')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Ma dette'))
    expect(await screen.findByText('REG-000001')).toBeInTheDocument()
  })

  it('démarre la préparation d’une commande confirmée', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/restaurateur/commandes') return COMMANDES as never
      return { 'general.whatsapp': null } as never
    })
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...COMMANDES[0], etat: 'en_preparation', etat_libelle: 'En préparation' } as never)

    monter('/restaurateur/commandes')

    await userEvent.click(await screen.findByText('Démarrer la préparation'))

    expect(envoyer).toHaveBeenCalledWith('/restaurateur/commandes/1/preparation')
  })
})
