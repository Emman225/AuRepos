import { useQuery } from '@tanstack/react-query'
import { Table } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { formaterDate } from '../../shared/format/date'
import { mesSejoursDuJour } from './api'
import type { SejourAgent } from './types'

/** Espace agent de terrain › Mes séjours du jour (CdC § 6.3) : arrivées confirmées à accueillir, départs arrivés à faire partir. */
export function PageSejours() {
  const { t } = useTranslation()
  const sejours = useQuery({ queryKey: ['agent', 'sejours'], queryFn: mesSejoursDuJour })

  return (
    <div>
      <EnTeteDePage titre={t('agent.menu.sejours')} />

      <Table<SejourAgent>
        rowKey="id"
        loading={sejours.isPending}
        dataSource={sejours.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('agent.sejours.aucun')} /> }}
        columns={[
          { title: t('client.sejours.reference'), render: (_, s) => <Link to={`${s.id}`}>{s.reference}</Link> },
          { title: t('backoffice.reservations.client'), render: (_, s) => s.client?.nom ?? '—' },
          { title: t('client.sejours.logement'), render: (_, s) => s.logement.nom },
          { title: t('client.sejours.arrivee'), dataIndex: 'arrivee', render: (v: string) => formaterDate(v) },
          { title: t('client.sejours.depart'), dataIndex: 'depart', render: (v: string) => formaterDate(v) },
          {
            title: t('backoffice.clients.statut'),
            render: (_, s) => <StatutBadge domaine="sejour" code={s.etat} libelle={s.etat_libelle} />,
          },
        ]}
      />
    </div>
  )
}
