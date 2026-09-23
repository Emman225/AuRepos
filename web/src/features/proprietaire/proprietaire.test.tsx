import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { RoutesApplication } from '../../routes/RoutesApplication'
import * as clientApi from '../../shared/api/client'
import '../../shared/i18n'
import { useSession } from '../auth/session'
import type { Utilisateur } from '../auth/types'
import type { CompteursProprietaire, LogementProprietaire, ResidenceProprietaire, SejourProprietaire } from './types'

Object.defineProperty(window, 'matchMedia', {
  value: () => ({
    matches: false,
    addListener() {},
    removeListener() {},
    addEventListener() {},
    removeEventListener() {},
  }),
})

const proprietaire = (): Utilisateur => ({
  id: 1,
  nom: 'Hubert',
  prenoms: 'Marcelle',
  nom_complet: 'Marcelle Hubert',
  email: 'marcelle@exemple.ci',
  telephone: null,
  profil: 'proprietaire',
  profil_libelle: 'Propriétaire',
  espace: 'proprietaire',
  agence: null,
  peut_encaisser: false,
})

const COMPTEURS: CompteursProprietaire = { nombre_residences: 1, nombre_logements: 3, sejours_en_cours: 0, arrivees_sous_7_jours: 2 }
const RESIDENCE: ResidenceProprietaire = {
  id: 9,
  nom: 'Résidence Rocher 542',
  lieu: { commune: 'Cocody', quartier: 'Angré', libelle: 'Cocody › Angré' },
  nombre_logements: 1,
  disponibilite: 'disponible',
  disponibilite_libelle: 'Disponible',
  active: true,
}
const LOGEMENT: LogementProprietaire = {
  id: 1,
  reference: 'LOG-00001',
  nom: 'Appartement x941',
  resume: '',
  capacite_de_base: 2,
  capacite_maximale: 4,
  etat_publication: 'publie',
  etat_publication_libelle: 'Publié',
}
const SEJOUR: SejourProprietaire = {
  reference: 'SEJ-000001',
  etat: 'demande',
  etat_libelle: 'Demandé',
  arrivee: '2026-11-10',
  depart: '2026-11-13',
  nombre_de_nuits: 3,
  client: 'Demo Client',
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

beforeEach(() => {
  vi.restoreAllMocks()
  useSession.setState({ jeton: 'jeton-test', utilisateur: proprietaire(), statut: 'connecte' })
})

describe('espace propriétaire', () => {
  it('affiche le tableau de bord puis navigue jusqu’au séjour d’un logement', async () => {
    vi.spyOn(clientApi, 'lire').mockImplementation(async (url: string) => {
      if (url === '/proprietaire/tableau-de-bord') return COMPTEURS as never
      if (url === '/proprietaire/residences') return [RESIDENCE] as never
      if (url === '/proprietaire/residences/9/logements') return [LOGEMENT] as never
      if (url === '/proprietaire/logements/1/sejours') return [SEJOUR] as never
      return { 'general.whatsapp': null } as never
    })

    monter('/proprietaire')

    expect(await screen.findByText('Bonjour Marcelle')).toBeInTheDocument()
    expect(await screen.findByText('1')).toBeInTheDocument()
    expect(screen.getByText('Résidences')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Mes résidences'))
    expect(await screen.findByText('Résidence Rocher 542')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Résidence Rocher 542'))
    expect(await screen.findByText('Appartement x941')).toBeInTheDocument()
    expect(screen.getByText('Publié')).toBeInTheDocument()

    await userEvent.click(screen.getByText('Appartement x941'))
    expect(await screen.findByText('SEJ-000001')).toBeInTheDocument()
    expect(screen.getByText('Demo Client')).toBeInTheDocument()
  })
})
