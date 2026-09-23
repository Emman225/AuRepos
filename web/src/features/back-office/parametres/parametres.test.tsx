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
import type { Banniere, DonneesParametres } from './types'

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

const PARAMETRES: DonneesParametres = {
  onglets: [
    {
      code: 'proprietaires',
      libelle: 'Propriétaires',
      parametres: [
        {
          cle: 'proprietaires.filigrane',
          nom: 'filigrane',
          libelle: 'Texte du filigrane des photos',
          type: 'texte',
          valeur: 'Ancien texte',
          defaut: null,
          options: null,
          aide: null,
          reserve_super_administrateur: false,
          double_validation: false,
        },
        {
          cle: 'proprietaires.pourcentage_entreprise',
          nom: 'pourcentage_entreprise',
          libelle: 'Pourcentage entreprise par défaut (%)',
          type: 'decimal',
          valeur: 20,
          defaut: 20,
          options: null,
          aide: 'Plancher de marge conseillé sur le prix propriétaire.',
          reserve_super_administrateur: false,
          double_validation: true,
        },
      ],
    },
  ],
  administrateurs: [],
  alertes: [],
}

const BANNIERE: Banniere = {
  id: 1,
  titre: 'Promo rentrée',
  sous_titre: null,
  image_url: 'https://exemple.ci/b.jpg',
  lien: null,
  ordre: 0,
  actif: true,
  cree_le: '01/09/2026 10:00:00',
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

describe('paramètres et divers (P1-BO-10)', () => {
  // Les onglets de Paramètres › Divers sont montés dès l'ouverture de l'écran (Tabs d'AntD ne
  // détruit pas les panneaux inactifs par défaut) : leurs requêtes partent donc même quand un
  // autre onglet est affiché, et doivent recevoir une forme de réponse correcte.
  function repondreSelonUrl() {
    return vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/backoffice/parametres') return PARAMETRES as never
      if (url === '/backoffice/bannieres') return [BANNIERE] as never
      if (url === '/backoffice/carrousel') return [] as never
      if (url === '/backoffice/articles') return { elements: [], pagination: { page: 1, par_page: 50, total: 0, derniere_page: 1 } } as never
      if (url === '/backoffice/newsletter') return { elements: [], pagination: { page: 1, par_page: 50, total: 0, derniere_page: 1 } } as never
      return { 'general.whatsapp': null } as never
    })
  }

  it('enregistre un onglet sans jamais renvoyer un réglage à double validation', async () => {
    repondreSelonUrl()
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ onglets: PARAMETRES.onglets } as never)

    monter('/admin/parametres')

    const champ = await screen.findByDisplayValue('Ancien texte')
    await userEvent.clear(champ)
    await userEvent.type(champ, 'Nouveau texte')
    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(envoyer).toHaveBeenCalledWith('/backoffice/parametres/proprietaires', { valeurs: { filigrane: 'Nouveau texte' } }, 'put')
  })

  it('liste les bannières et en crée une nouvelle depuis Paramètres › Divers', async () => {
    repondreSelonUrl()
    const envoyer = vi.spyOn(clientApi, 'envoyer').mockResolvedValue({ ...BANNIERE, id: 2, titre: 'Nouvelle offre' } as never)

    monter('/admin/parametres')
    await userEvent.click(await screen.findByText('Divers'))
    await userEvent.click(screen.getByText('Bannières'))

    expect(await screen.findByText('Promo rentrée')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Nouvelle bannière'))
    const champs = await screen.findAllByRole('textbox')
    await userEvent.type(champs[0], 'Nouvelle offre')
    await userEvent.type(champs[2], 'https://exemple.ci/c.jpg')

    await userEvent.click(screen.getByRole('button', { name: 'Enregistrer' }))

    expect(envoyer).toHaveBeenCalledWith(
      '/backoffice/bannieres',
      { titre: 'Nouvelle offre', sous_titre: undefined, image_url: 'https://exemple.ci/c.jpg', lien: undefined, ordre: 0, actif: true },
    )
  })
})
