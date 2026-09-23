import { App as AppAntd, Button } from 'antd'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useState } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import * as clientApi from '../api/client'
import '../i18n'
import { useConfirmerAction } from './confirmer'
import { TableauDeListe } from './TableauDeListe'

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

// jsdom n'a pas ResizeObserver, dont Table (défilement) a besoin.
class ResizeObserverSimule {
  observe() {}
  unobserve() {}
  disconnect() {}
}
vi.stubGlobal('ResizeObserver', ResizeObserverSimule)

interface Ligne {
  id: number
  nom: string
}

const LIGNES: Ligne[] = [
  { id: 1, nom: 'Première ligne' },
  { id: 2, nom: 'Seconde ligne' },
]

beforeEach(() => {
  vi.restoreAllMocks()
})

describe('TableauDeListe (P1-WEB-04)', () => {
  it('affiche le titre et les lignes', () => {
    render(
      <AppAntd>
        <TableauDeListe
          titre="Ma liste"
          dataSource={LIGNES}
          rowKey="id"
          columns={[{ title: 'Nom', dataIndex: 'nom' }]}
        />
      </AppAntd>,
    )

    expect(screen.getByRole('heading', { name: 'Ma liste' })).toBeInTheDocument()
    expect(screen.getByText('Première ligne')).toBeInTheDocument()
    expect(screen.getByText('Seconde ligne')).toBeInTheDocument()
  })

  it('signale le changement de période au conteneur, en saisissant les dates au clavier', async () => {
    const utilisateur = userEvent.setup({ delay: null })
    const onPeriodeChange = vi.fn()
    render(
      <AppAntd>
        <TableauDeListe
          titre="Ma liste"
          dataSource={LIGNES}
          rowKey="id"
          columns={[{ title: 'Nom', dataIndex: 'nom' }]}
          periode={{ du: null, au: null }}
          onPeriodeChange={onPeriodeChange}
        />
      </AppAntd>,
    )

    await utilisateur.type(screen.getByPlaceholderText('Du'), '01/11/2026{Enter}')
    await utilisateur.type(screen.getByPlaceholderText('Au'), '30/11/2026{Enter}')

    await waitFor(() => expect(onPeriodeChange).toHaveBeenCalledWith({ du: '2026-11-01', au: '2026-11-30' }))
  })

  it('exporte avec les filtres de période et les filtres propres à l’écran', async () => {
    const telecharger = vi.spyOn(clientApi, 'telechargerExport').mockResolvedValue()
    render(
      <AppAntd>
        <TableauDeListe
          titre="Caisse"
          dataSource={LIGNES}
          rowKey="id"
          columns={[{ title: 'Nom', dataIndex: 'nom' }]}
          periode={{ du: '2026-11-01', au: '2026-11-30' }}
          onPeriodeChange={() => {}}
          urlExport="/backoffice/caisse/export"
          filtresExport={{ guichet: 'sejours' }}
        />
      </AppAntd>,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Excel' }))

    await waitFor(() =>
      expect(telecharger).toHaveBeenCalledWith(
        '/backoffice/caisse/export',
        { guichet: 'sejours', du: '2026-11-01', au: '2026-11-30' },
        'xlsx',
        'Caisse',
      ),
    )
  })

  it('affiche un message d’erreur si l’export échoue', async () => {
    vi.spyOn(clientApi, 'telechargerExport').mockRejectedValue(new clientApi.ErreurApi('Format d’export inconnu.', 422))
    render(
      <AppAntd>
        <TableauDeListe
          titre="Caisse"
          dataSource={LIGNES}
          rowKey="id"
          columns={[{ title: 'Nom', dataIndex: 'nom' }]}
          urlExport="/x/export"
        />
      </AppAntd>,
    )

    await userEvent.click(screen.getByRole('button', { name: 'PDF' }))

    expect(await screen.findByText('Format d’export inconnu.')).toBeInTheDocument()
  })
})

function BoutonDeTest() {
  const confirmer = useConfirmerAction()
  const [resultat, setResultat] = useState<string>('')

  return (
    <>
      <Button
        onClick={async () => {
          const ok = await confirmer({
            titre: 'Supprimer ce compte ?',
            contenu: 'Cette action est irréversible.',
            danger: true,
          })
          setResultat(ok ? 'confirme' : 'annule')
        }}
      >
        Supprimer
      </Button>
      <span data-testid="resultat">{resultat}</span>
    </>
  )
}

describe('useConfirmerAction (P1-WEB-04)', () => {
  it('rend une modale, jamais window.confirm, et résout selon le choix', async () => {
    const confirmNatif = vi.spyOn(window, 'confirm')
    render(
      <AppAntd>
        <BoutonDeTest />
      </AppAntd>,
    )

    await userEvent.click(screen.getByRole('button', { name: 'Supprimer' }))
    // Antd 6 répète le titre (en-tête accessible + corps visuel) : on vérifie sa présence, pas son unicité.
    expect(await screen.findAllByText('Supprimer ce compte ?')).not.toHaveLength(0)
    expect(screen.getByText('Cette action est irréversible.')).toBeInTheDocument()
    expect(confirmNatif).not.toHaveBeenCalled()

    await userEvent.click(screen.getByRole('button', { name: 'Confirmer' }))
    await waitFor(() => expect(screen.getByTestId('resultat')).toHaveTextContent('confirme'))
  })
})
