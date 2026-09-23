import { useQuery } from '@tanstack/react-query'
import { Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { listerLesTicketsAssistance } from './api'
import type { EtatDuTicket, TicketAssistance } from './types'

const ETATS: EtatDuTicket[] = ['ouvert', 'en_cours', 'ferme']

/**
 * Back office › Tickets d'assistance (P2-AST-01, CdC § 6.4) : consultation seule. La réponse et la
 * fermeture d'un ticket se font dans l'espace assistance (routes/api_v1/assistance.php,
 * `Assistance\TicketsController`), pas ici — aucun bouton d'action n'est donc affiché.
 */
export function PageTicketsAssistance() {
  const { t } = useTranslation()
  const [statut, setStatut] = useState<EtatDuTicket | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()

  const filtres = { statut, page, par_page: parPage }
  const tickets = useQuery({ queryKey: ['backoffice', 'tickets-assistance', filtres], queryFn: () => listerLesTicketsAssistance(filtres) })

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.ticketsAssistance')} description={t('backoffice.ticketsAssistance.consultationSeule')} />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.ticketsAssistance.tousLesStatuts')}
          value={statut}
          onChange={(v) => {
            setStatut(v)
            reinitialiser()
          }}
          options={ETATS.map((e) => ({ value: e, label: t(`backoffice.ticketsAssistance.statuts.${e}`) }))}
        />
      </Space>

      <Table<TicketAssistance>
        rowKey="id"
        loading={tickets.isPending}
        dataSource={tickets.data?.elements}
        pagination={propsPagination(tickets.data?.pagination.total)}
        locale={{ emptyText: <EtatVide titre={t('backoffice.ticketsAssistance.aucun')} /> }}
        columns={[
          { title: t('backoffice.ticketsAssistance.sejour'), render: (_, tk) => tk.sejour?.reference ?? '—' },
          { title: t('backoffice.reservations.client'), dataIndex: 'client' },
          { title: t('backoffice.ticketsAssistance.sujet'), dataIndex: 'sujet' },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'statut',
            render: (_, tk) => <StatutBadge domaine="ticketAssistance" code={tk.statut} libelle={tk.statut_libelle} />,
          },
          {
            title: t('backoffice.ticketsAssistance.reponse'),
            dataIndex: 'reponse',
            render: (v: string | null) => v ?? <Typography.Text type="secondary">—</Typography.Text>,
          },
          { title: t('backoffice.ticketsAssistance.creeLe'), dataIndex: 'created_at', render: (v: string | null) => v ?? '—' },
        ]}
      />
    </div>
  )
}
