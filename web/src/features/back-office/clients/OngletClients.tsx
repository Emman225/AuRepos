import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Select, Space, Switch, Table, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { basculerLaListeNoire, basculerLaTva, listerLesClients, modifierLaFicheDuClient } from './api'
import type { Client, StatutCompte } from './types'

/** Back office › Clients : liste, inscriptions en attente, bascules de TVA, liste noire (CdC § 5). */
export function OngletClients() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [statut, setStatut] = useState<StatutCompte | undefined>(undefined)
  const [listeNoire, setListeNoire] = useState<boolean | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [clientOuvert, setClientOuvert] = useState<Client | null>(null)

  const filtres = { recherche: recherche || undefined, statut, liste_noire: listeNoire, page, par_page: parPage }
  const clients = useQuery({ queryKey: ['backoffice', 'clients', filtres], queryFn: () => listerLesClients(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'clients'] })

  return (
    <div>
      <Space wrap style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
        <Space wrap>
          <Input
            placeholder={t('backoffice.clients.recherche')}
            value={recherche}
            onChange={(e) => {
              setRecherche(e.target.value)
              reinitialiser()
            }}
            style={{ width: 240 }}
            allowClear
          />
          <Select
            style={{ width: 200 }}
            allowClear
            placeholder={t('backoffice.clients.statut')}
            value={statut}
            onChange={(v) => {
              setStatut(v)
              reinitialiser()
            }}
            options={(['en_attente', 'actif', 'bloque'] as StatutCompte[]).map((s) => ({
              value: s,
              label: t(`backoffice.clients.statuts.${s}`),
            }))}
          />
          <Select
            style={{ width: 200 }}
            allowClear
            placeholder={t('backoffice.clients.listeNoire')}
            value={listeNoire}
            onChange={(v) => {
              setListeNoire(v)
              reinitialiser()
            }}
            options={[
              { value: true, label: t('backoffice.clients.enListeNoire') },
              { value: false, label: t('backoffice.clients.pasEnListeNoire') },
            ]}
          />
        </Space>
        <BoutonsExport
          url="/backoffice/clients/export"
          filtres={{ recherche: recherche || undefined, statut, liste_noire: listeNoire }}
          nomFichier="Clients"
        />
      </Space>

      <Table<Client>
        rowKey="id"
        size="small"
        loading={clients.isPending}
        dataSource={clients.data?.elements}
        pagination={propsPagination(clients.data?.pagination.total)}
        onRow={(c) => ({ onClick: () => setClientOuvert(c), style: { cursor: 'pointer' } })}
        columns={[
          { title: t('backoffice.clients.nom'), dataIndex: 'nom_complet' },
          { title: t('backoffice.clients.email'), dataIndex: 'email' },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'statut_libelle',
            render: (v: string, c) => <StatutBadge domaine="compte" code={c.statut} libelle={v} />,
          },
          {
            title: t('backoffice.clients.nature'),
            dataIndex: 'nature_libelle',
            render: (v: string | null) => v ?? '—',
          },
          {
            title: t('backoffice.clients.statutATerme'),
            dataIndex: 'statut_a_terme_libelle',
            render: (v: string | null) => v ?? '—',
          },
          {
            title: t('backoffice.clients.listeNoire'),
            dataIndex: 'liste_noire',
            render: (v: boolean) => (v ? <Tag color="red">{t('backoffice.clients.enListeNoire')}</Tag> : '—'),
          },
        ]}
      />

      <Modal
        title={clientOuvert?.nom_complet}
        open={clientOuvert !== null}
        onCancel={() => setClientOuvert(null)}
        footer={null}
        destroyOnHidden
        width={520}
      >
        {clientOuvert && (
          <DetailClient
            client={clientOuvert}
            onMisAJour={(c) => {
              setClientOuvert(c)
              void invalider()
            }}
          />
        )}
      </Modal>
    </div>
  )
}

function DetailClient({ client, onMisAJour }: { client: Client; onMisAJour: (c: Client) => void }) {
  const { t } = useTranslation()
  const [motifListeNoire, setMotifListeNoire] = useState('')
  const [motifTva, setMotifTva] = useState('')
  const [raisonSociale, setRaisonSociale] = useState(client.raison_sociale ?? '')
  const [ncc, setNcc] = useState(client.ncc ?? '')
  const [rccm, setRccm] = useState(client.rccm ?? '')
  const [erreur, setErreur] = useState<string | null>(null)

  const surErreur = (e: unknown) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
  const motifTvaValide = motifTva.trim().length >= 5

  const tva = useMutation({
    mutationFn: (saisie: { tva_hebergement?: boolean; tva_transfert?: boolean }) =>
      basculerLaTva(client.id, { ...saisie, motif: motifTva }),
    onSuccess: (c) => {
      onMisAJour(c)
      setMotifTva('')
    },
    onError: surErreur,
  })
  const listeNoire = useMutation({
    mutationFn: (v: boolean) => basculerLaListeNoire(client.id, v, v ? motifListeNoire : undefined),
    onSuccess: (c) => {
      onMisAJour(c)
      setMotifListeNoire('')
    },
    onError: surErreur,
  })
  const fiche = useMutation({
    mutationFn: () => modifierLaFicheDuClient(client.id, { raison_sociale: raisonSociale, ncc, rccm }),
    onSuccess: onMisAJour,
    onError: surErreur,
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }} size="middle">
      <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
        {client.email} — {client.telephone ?? '—'}
      </Typography.Paragraph>

      {erreur && <Alert type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text strong>{t('backoffice.clients.fiche.titre')}</Typography.Text>
        <Input
          placeholder={t('backoffice.clients.fiche.raisonSociale')}
          value={raisonSociale}
          onChange={(e) => setRaisonSociale(e.target.value)}
        />
        <Input placeholder={t('backoffice.clients.fiche.ncc')} value={ncc} onChange={(e) => setNcc(e.target.value)} />
        <Input placeholder={t('backoffice.clients.fiche.rccm')} value={rccm} onChange={(e) => setRccm(e.target.value)} />
        <Button loading={fiche.isPending} onClick={() => fiche.mutate()}>
          {t('backoffice.clients.fiche.enregistrer')}
        </Button>
      </Space>

      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text strong>{t('backoffice.clients.tva.titre')}</Typography.Text>
        <Input
          placeholder={t('backoffice.clients.tva.motif')}
          value={motifTva}
          onChange={(e) => setMotifTva(e.target.value)}
        />
        {client.tva_motif && (
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
            {t('backoffice.clients.tva.dernierMotif')} : {client.tva_motif}
            {client.tva_motif_le ? ` — ${client.tva_motif_le}` : ''}
          </Typography.Text>
        )}
        <Space>
          <Switch
            checked={client.tva_hebergement ?? false}
            disabled={!motifTvaValide}
            loading={tva.isPending}
            onChange={(v) => tva.mutate({ tva_hebergement: v })}
          />
          <Typography.Text>{t('backoffice.clients.tva.hebergement')}</Typography.Text>
        </Space>
        <Space>
          <Switch
            checked={client.tva_transfert ?? false}
            disabled={!motifTvaValide}
            loading={tva.isPending}
            onChange={(v) => tva.mutate({ tva_transfert: v })}
          />
          <Typography.Text>{t('backoffice.clients.tva.transfert')}</Typography.Text>
        </Space>
      </Space>

      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text strong>{t('backoffice.clients.listeNoire')}</Typography.Text>
        {client.liste_noire ? (
          <>
            <Typography.Paragraph type="danger" style={{ margin: 0 }}>
              {client.liste_noire_motif}
              {client.liste_noire_le ? ` — ${client.liste_noire_le}` : ''}
            </Typography.Paragraph>
            <Button danger loading={listeNoire.isPending} onClick={() => listeNoire.mutate(false)}>
              {t('backoffice.clients.retirerDeLaListeNoire')}
            </Button>
          </>
        ) : (
          <>
            <Input.TextArea
              placeholder={t('backoffice.clients.motifListeNoire')}
              value={motifListeNoire}
              onChange={(e) => setMotifListeNoire(e.target.value)}
              rows={2}
            />
            <Button
              danger
              loading={listeNoire.isPending}
              disabled={motifListeNoire.trim().length < 5}
              onClick={() => listeNoire.mutate(true)}
            >
              {t('backoffice.clients.mettreEnListeNoire')}
            </Button>
          </>
        )}
      </Space>
    </Space>
  )
}
