import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Popconfirm, Select, Space, Switch, Table, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { creerUneDiapositive, listerLeCarrousel, modifierUneDiapositive, supprimerUneDiapositive } from './api'
import type { Diapositive, SaisieDiapositive } from './types'

/** Paramètres › Divers › Carrousel (CdC § 12, P1-BO-10) : visuel d'en-tête de la page d'accueil. */
export function OngletCarrousel() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [actif, setActif] = useState<boolean | undefined>(undefined)
  const [nouvelleOuverte, setNouvelleOuverte] = useState(false)
  const [diapositiveOuverte, setDiapositiveOuverte] = useState<Diapositive | null>(null)

  const diapositives = useQuery({ queryKey: ['backoffice', 'carrousel'], queryFn: listerLeCarrousel })
  const elements = (diapositives.data ?? []).filter((d) => actif === undefined || d.actif === actif)

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'carrousel'] })

  const supprimer = useMutation({
    mutationFn: (id: number) => supprimerUneDiapositive(id),
    onSuccess: () => void invalider(),
  })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Select
          style={{ width: 180 }}
          allowClear
          placeholder={t('backoffice.parametres.bannieres.actif')}
          value={actif}
          onChange={setActif}
          options={[
            { value: true, label: t('backoffice.agences.active') },
            { value: false, label: t('backoffice.agences.inactive') },
          ]}
        />
        <Space>
          <BoutonsExport url="/backoffice/carrousel/export" filtres={{ actif }} nomFichier="Carrousel" />
          <Button type="primary" onClick={() => setNouvelleOuverte(true)}>
            {t('backoffice.parametres.carrousel.nouvelle')}
          </Button>
        </Space>
      </div>

      <Table<Diapositive>
        rowKey="id"
        size="small"
        loading={diapositives.isPending}
        dataSource={elements}
        pagination={{ pageSize: 5, showSizeChanger: true, pageSizeOptions: [5, 10, 20, 50, 100] }}
        onRow={(d) => ({ onClick: () => setDiapositiveOuverte(d), style: { cursor: 'pointer' } })}
        columns={[
          { title: t('backoffice.parametres.carrousel.legende'), dataIndex: 'legende', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.parametres.bannieres.ordre'), dataIndex: 'ordre' },
          {
            title: t('backoffice.parametres.bannieres.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => <Tag color={v ? 'green' : 'default'}>{v ? t('backoffice.agences.active') : t('backoffice.agences.inactive')}</Tag>,
          },
          {
            title: '',
            key: 'actions',
            render: (_, d) => (
              <Popconfirm
                title={t('backoffice.parametres.bannieres.confirmerSuppression')}
                onConfirm={(e) => { e?.stopPropagation(); supprimer.mutate(d.id) }}
                onCancel={(e) => e?.stopPropagation()}
              >
                <Button danger size="small" onClick={(e) => e.stopPropagation()}>
                  {t('backoffice.parametres.bannieres.supprimer')}
                </Button>
              </Popconfirm>
            ),
          },
        ]}
      />

      <Modal title={t('backoffice.parametres.carrousel.nouvelle')} open={nouvelleOuverte} onCancel={() => setNouvelleOuverte(false)} footer={null} destroyOnHidden>
        <FormulaireDiapositive diapositive={null} onEnregistree={() => { setNouvelleOuverte(false); void invalider() }} />
      </Modal>

      <Modal title={t('backoffice.parametres.carrousel.diapositive')} open={diapositiveOuverte !== null} onCancel={() => setDiapositiveOuverte(null)} footer={null} destroyOnHidden>
        {diapositiveOuverte && (
          <FormulaireDiapositive diapositive={diapositiveOuverte} onEnregistree={() => { setDiapositiveOuverte(null); void invalider() }} />
        )}
      </Modal>
    </div>
  )
}

function FormulaireDiapositive({ diapositive, onEnregistree }: { diapositive: Diapositive | null; onEnregistree: () => void }) {
  const { t } = useTranslation()
  const [imageUrl, setImageUrl] = useState(diapositive?.image_url ?? '')
  const [legende, setLegende] = useState(diapositive?.legende ?? '')
  const [lien, setLien] = useState(diapositive?.lien ?? '')
  const [ordre, setOrdre] = useState(diapositive?.ordre ?? 0)
  const [actif, setActif] = useState(diapositive?.actif ?? true)
  const [erreur, setErreur] = useState<string | null>(null)

  const enregistrer = useMutation({
    mutationFn: () => {
      const saisie: SaisieDiapositive = { image_url: imageUrl, legende: legende || undefined, lien: lien || undefined, ordre, actif }
      return diapositive ? modifierUneDiapositive(diapositive.id, saisie) : creerUneDiapositive(saisie)
    },
    onSuccess: onEnregistree,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.parametres.bannieres.image')}</Typography.Text>
      <Input value={imageUrl} onChange={(e) => setImageUrl(e.target.value)} placeholder="https://…" />
      <Typography.Text>{t('backoffice.parametres.carrousel.legende')}</Typography.Text>
      <Input value={legende} onChange={(e) => setLegende(e.target.value)} />
      <Typography.Text>{t('backoffice.parametres.bannieres.lien')}</Typography.Text>
      <Input value={lien} onChange={(e) => setLien(e.target.value)} placeholder="https://…" />
      <Typography.Text>{t('backoffice.parametres.bannieres.ordre')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} value={ordre} onChange={(v) => setOrdre(v ?? 0)} min={0} />
      <Space>
        <Switch checked={actif} onChange={setActif} />
        <Typography.Text>{t('backoffice.parametres.bannieres.actif')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} disabled={!imageUrl} onClick={() => enregistrer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
