import { useQuery } from '@tanstack/react-query'
import { Table } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { formaterPrix } from '../../shared/format/devise'
import { mesCommissions } from './api'
import type { CommissionApporteur } from './types'

/** Espace apporteur › Mes commissions (lecture seule) : le solde dû se lit sur le tableau de bord, le reversement passe par l'agence. */
export function PageCommissions() {
  const { t } = useTranslation()
  const commissions = useQuery({ queryKey: ['apporteur', 'commissions'], queryFn: mesCommissions })

  return (
    <div>
      <EnTeteDePage titre={t('apporteur.menu.commissions')} />
      <Table<CommissionApporteur>
        rowKey="id"
        loading={commissions.isPending}
        dataSource={commissions.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('apporteur.commissions.aucune')} /> }}
        columns={[
          { title: t('client.sejours.reference'), dataIndex: 'sejour_reference', render: (v: string | null) => v ?? '—' },
          { title: t('apporteur.commissions.date'), dataIndex: 'cree_le' },
          { title: t('apporteur.commissions.montant'), dataIndex: 'montant', render: (v: number) => formaterPrix(v) },
        ]}
      />
    </div>
  )
}
