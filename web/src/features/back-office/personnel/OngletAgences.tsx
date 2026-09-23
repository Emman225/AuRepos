import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Select, Space, Switch, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { creerUneAgence, listerLesAgences, modifierUneAgence } from './api'
import type { Agence } from './types'

/** Back office › Agences (CdC § 8.1, 9.5). */
export function OngletAgences() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [active, setActive] = useState<boolean | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [nouvelleOuverte, setNouvelleOuverte] = useState(false)
  const [agenceOuverte, setAgenceOuverte] = useState<Agence | null>(null)

  const filtres = { recherche: recherche || undefined, active, page, par_page: parPage }
  const agences = useQuery({ queryKey: ['backoffice', 'agences', filtres], queryFn: () => listerLesAgences(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'agences'] })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Space wrap>
          <Input.Search
            placeholder={t('backoffice.agences.rechercher')}
            value={recherche}
            onChange={(e) => {
              setRecherche(e.target.value)
              reinitialiser()
            }}
            style={{ width: 260 }}
            allowClear
          />
          <Select
            style={{ width: 160 }}
            allowClear
            placeholder={t('backoffice.agences.active')}
            value={active}
            onChange={(v) => {
              setActive(v)
              reinitialiser()
            }}
            options={[
              { value: true, label: t('backoffice.agences.active') },
              { value: false, label: t('backoffice.agences.inactive') },
            ]}
          />
        </Space>
        <Space>
          <BoutonsExport url="/backoffice/agences/export" filtres={{ recherche: recherche || undefined, active }} nomFichier="Agences" />
          <Button type="primary" onClick={() => setNouvelleOuverte(true)}>
            {t('backoffice.agences.nouvelle')}
          </Button>
        </Space>
      </div>

      <Table<Agence>
        rowKey="id"
        size="small"
        loading={agences.isPending}
        dataSource={agences.data?.elements}
        pagination={propsPagination(agences.data?.pagination.total)}
        onRow={(a) => ({ onClick: () => setAgenceOuverte(a), style: { cursor: 'pointer' } })}
        columns={[
          { title: t('backoffice.agences.nom'), dataIndex: 'nom' },
          { title: t('backoffice.agences.adresse'), dataIndex: 'adresse', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.agences.telephone'), dataIndex: 'telephone', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.agences.nombrePersonnel'), dataIndex: 'nombre_utilisateurs', render: (v: number | null) => v ?? 0 },
          {
            title: t('backoffice.agences.active'),
            dataIndex: 'active',
            render: (v: boolean) => <StatutBadge domaine="actif" code={v ? 'actif' : 'inactif'} libelle={v ? t('backoffice.agences.active') : t('backoffice.agences.inactive')} />,
          },
        ]}
      />

      <Modal title={t('backoffice.agences.nouvelle')} open={nouvelleOuverte} onCancel={() => setNouvelleOuverte(false)} footer={null} destroyOnHidden>
        <FormulaireAgence
          agence={null}
          onEnregistree={() => {
            setNouvelleOuverte(false)
            void invalider()
          }}
        />
      </Modal>

      <Modal title={agenceOuverte?.nom} open={agenceOuverte !== null} onCancel={() => setAgenceOuverte(null)} footer={null} destroyOnHidden>
        {agenceOuverte && (
          <FormulaireAgence
            agence={agenceOuverte}
            onEnregistree={() => {
              setAgenceOuverte(null)
              void invalider()
            }}
          />
        )}
      </Modal>
    </div>
  )
}

function FormulaireAgence({ agence, onEnregistree }: { agence: Agence | null; onEnregistree: () => void }) {
  const { t } = useTranslation()
  const [nom, setNom] = useState(agence?.nom ?? '')
  const [adresse, setAdresse] = useState(agence?.adresse ?? '')
  const [telephone, setTelephone] = useState(agence?.telephone ?? '')
  const [active, setActive] = useState(agence?.active ?? true)
  const [erreur, setErreur] = useState<string | null>(null)

  const enregistrer = useMutation({
    mutationFn: () => {
      const saisie = { nom, adresse: adresse || undefined, telephone: telephone || undefined, active }
      return agence ? modifierUneAgence(agence.id, saisie) : creerUneAgence(saisie)
    },
    onSuccess: onEnregistree,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.agences.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.agences.adresse')}</Typography.Text>
      <Input value={adresse} onChange={(e) => setAdresse(e.target.value)} />
      <Typography.Text>{t('backoffice.agences.telephone')}</Typography.Text>
      <Input value={telephone} onChange={(e) => setTelephone(e.target.value)} placeholder="+2250700000000" />
      <Space>
        <Switch checked={active} onChange={setActive} />
        <Typography.Text>{t('backoffice.agences.active')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} disabled={!nom} onClick={() => enregistrer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
