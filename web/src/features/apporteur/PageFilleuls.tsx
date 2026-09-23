import { useQuery } from '@tanstack/react-query'
import { Table } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { mesFilleuls } from './api'
import type { Filleul } from './types'

/** Espace apporteur › Mes filleuls (lecture seule). */
export function PageFilleuls() {
  const { t } = useTranslation()
  const filleuls = useQuery({ queryKey: ['apporteur', 'filleuls'], queryFn: mesFilleuls })

  return (
    <div>
      <EnTeteDePage titre={t('apporteur.menu.filleuls')} />
      <Table<Filleul>
        rowKey="id"
        loading={filleuls.isPending}
        dataSource={filleuls.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('apporteur.filleuls.aucun')} /> }}
        columns={[
          { title: t('backoffice.proprietaires.nom'), dataIndex: 'nom_complet' },
          { title: t('apporteur.filleuls.inscritLe'), dataIndex: 'inscrit_le', render: (v: string | null) => v ?? '—' },
        ]}
      />
    </div>
  )
}
