import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Input, Popconfirm, Select, Space, Table, Tag } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { usePagination } from '../../../shared/composants/usePagination'
import { desabonnerDeLaNewsletter, listerLesAbonnesNewsletter } from './api'
import type { AbonneNewsletter } from './types'

/** Paramètres › Divers › Lettre d'information (CdC § 12, P1-BO-10) : abonnés, en libre-service côté public. */
export function OngletNewsletter() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [actif, setActif] = useState<boolean | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()

  const filtres = { recherche: recherche || undefined, actif, page, par_page: parPage }
  const abonnes = useQuery({ queryKey: ['backoffice', 'newsletter', filtres], queryFn: () => listerLesAbonnesNewsletter(filtres) })

  const desabonner = useMutation({
    mutationFn: (id: number) => desabonnerDeLaNewsletter(id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['backoffice', 'newsletter'] }),
  })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Space wrap>
          <Input.Search
            placeholder={t('backoffice.parametres.newsletter.rechercher')}
            value={recherche}
            onChange={(e) => {
              setRecherche(e.target.value)
              reinitialiser()
            }}
            style={{ width: 260 }}
            allowClear
          />
          <Select
            style={{ width: 180 }}
            allowClear
            placeholder={t('backoffice.parametres.bannieres.actif')}
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
        </Space>
        <BoutonsExport url="/backoffice/newsletter/export" filtres={{ recherche: recherche || undefined, actif }} nomFichier="Newsletter" />
      </div>

      <Table<AbonneNewsletter>
        rowKey="id"
        size="small"
        loading={abonnes.isPending}
        dataSource={abonnes.data?.elements}
        pagination={propsPagination(abonnes.data?.pagination.total)}
        columns={[
          { title: t('backoffice.parametres.newsletter.email'), dataIndex: 'email' },
          { title: t('backoffice.personnel.nom'), dataIndex: 'nom', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.parametres.newsletter.abonneLe'), dataIndex: 'abonne_le' },
          {
            title: t('backoffice.parametres.bannieres.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => <Tag color={v ? 'green' : 'default'}>{v ? t('backoffice.agences.active') : t('backoffice.agences.inactive')}</Tag>,
          },
          {
            title: '',
            key: 'actions',
            render: (_, a) =>
              a.actif && (
                <Popconfirm title={t('backoffice.parametres.newsletter.confirmerDesabonnement')} onConfirm={() => desabonner.mutate(a.id)}>
                  <Button danger size="small">
                    {t('backoffice.parametres.newsletter.desabonner')}
                  </Button>
                </Popconfirm>
              ),
          },
        ]}
      />
    </div>
  )
}
