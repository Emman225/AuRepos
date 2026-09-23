import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Popconfirm, Select, Space, Table, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi, lire } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { desactiverUnPrixNegocie, enregistrerUnPrixNegocie, listerLesPrixNegocies } from './api'
import type { PrixNegocie } from './types'

interface TypeLogementChoix {
  id: number
  nom: string
}

/** Back office › Tarification › Prix négociés (CdC § 7.3, réservé administrateur). */
export function OngletPrixNegocies() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [actif, setActif] = useState<boolean | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)

  const filtres = { actif, page, par_page: parPage }
  const prixNegocies = useQuery({ queryKey: ['backoffice', 'tarification', 'prix-negocies', filtres], queryFn: () => listerLesPrixNegocies(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'tarification', 'prix-negocies'] })

  const desactiver = useMutation({
    mutationFn: (id: number) => desactiverUnPrixNegocie(id),
    onSuccess: invalider,
  })

  return (
    <div>
      <Space wrap style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
        <Select
          style={{ width: 180 }}
          allowClear
          placeholder={t('backoffice.agences.active')}
          value={actif}
          onChange={(v) => {
            setActif(v)
            reinitialiser()
          }}
          options={[
            { value: true, label: t('backoffice.agences.active') },
            { value: false, label: t('backoffice.agences.inactive') },
          ]}
        />
        <Space>
          <BoutonsExport url="/backoffice/prix-negocies/export" filtres={{ actif }} nomFichier="PrixNegocies" />
          <Button type="primary" onClick={() => setFormulaireOuvert(true)}>
            {t('backoffice.tarification.prixNegocies.ajouter')}
          </Button>
        </Space>
      </Space>

      <Table<PrixNegocie>
        rowKey="id"
        size="small"
        loading={prixNegocies.isPending}
        dataSource={prixNegocies.data?.elements}
        pagination={propsPagination(prixNegocies.data?.pagination.total)}
        columns={[
          { title: t('backoffice.tarification.prixNegocies.client'), dataIndex: ['client', 'nom'] },
          { title: t('backoffice.tarification.prixNegocies.typeLogement'), dataIndex: ['type_logement', 'nom'] },
          { title: t('backoffice.tarification.prixNegocies.tarifParNuit'), dataIndex: 'tarif_par_nuit', render: (v: number) => formaterPrix(v) },
          {
            title: t('backoffice.tarification.prixNegocies.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => <Tag color={v ? 'green' : 'default'}>{v ? t('backoffice.tarification.prixNegocies.actif') : '—'}</Tag>,
          },
          { title: t('backoffice.tarification.prixNegocies.modifieLe'), dataIndex: 'modifie_le' },
          {
            title: '',
            key: 'actions',
            render: (_, p) =>
              p.actif ? (
                <Popconfirm
                  title={t('backoffice.tarification.prixNegocies.confirmerDesactivation')}
                  onConfirm={() => desactiver.mutate(p.id)}
                >
                  <Button size="small" danger>
                    {t('backoffice.tarification.prixNegocies.desactiver')}
                  </Button>
                </Popconfirm>
              ) : null,
          },
        ]}
      />

      <Modal
        title={t('backoffice.tarification.prixNegocies.ajouter')}
        open={formulaireOuvert}
        onCancel={() => setFormulaireOuvert(false)}
        footer={null}
        destroyOnHidden
      >
        <FormulairePrixNegocie
          onEnregistre={() => {
            setFormulaireOuvert(false)
            void invalider()
          }}
        />
      </Modal>
    </div>
  )
}

function FormulairePrixNegocie({ onEnregistre }: { onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [clientId, setClientId] = useState<number | null>(null)
  const [typeLogementId, setTypeLogementId] = useState<number | undefined>(undefined)
  const [tarif, setTarif] = useState<number | null>(null)
  const [notes, setNotes] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const types = useQuery({ queryKey: ['referentiels', 'types-logement'], queryFn: () => lire<TypeLogementChoix[]>('/referentiels/types-logement') })

  const enregistrer = useMutation({
    mutationFn: () =>
      enregistrerUnPrixNegocie({
        client_id: clientId!,
        type_logement_id: typeLogementId!,
        tarif_par_nuit: tarif!,
        notes: notes || undefined,
      }),
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.tarification.prixNegocies.client')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} value={clientId} onChange={setClientId} />
      <Typography.Text>{t('backoffice.tarification.prixNegocies.typeLogement')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={typeLogementId}
        onChange={setTypeLogementId}
        loading={types.isPending}
        options={types.data?.map((v) => ({ value: v.id, label: v.nom }))}
      />
      <Typography.Text>{t('backoffice.tarification.prixNegocies.tarifParNuit')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} step={1000} value={tarif} onChange={setTarif} />
      <Typography.Text>{t('backoffice.tarification.prixNegocies.notes')}</Typography.Text>
      <Input.TextArea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button
        type="primary"
        loading={enregistrer.isPending}
        disabled={!clientId || !typeLogementId || !tarif}
        onClick={() => enregistrer.mutate()}
      >
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
