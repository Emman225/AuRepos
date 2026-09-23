import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterDate } from '../../../shared/format/date'
import { formaterPrix } from '../../../shared/format/devise'
import { listerLesDemandesAnnulation } from './api'
import { FormulaireDecisionAnnulation } from './FormulaireDecisionAnnulation'
import type { DemandeAnnulation, EtatDeLaDemandeAnnulation } from './types'

const ETATS: EtatDeLaDemandeAnnulation[] = ['en_attente', 'acceptee', 'rejetee']

/** Back office › Demandes d'annulation (P2-SEJ-06, CdC § 6.1) : retenue / remboursement calculés côté API, décision ici. */
export function PageDemandesAnnulation() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [etat, setEtat] = useState<EtatDeLaDemandeAnnulation | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [decision, setDecision] = useState<{ demande: DemandeAnnulation; type: 'acceptation' | 'rejet' } | null>(null)

  const filtres = { etat, page, par_page: parPage }
  const demandes = useQuery({ queryKey: ['backoffice', 'demandes-annulation', filtres], queryFn: () => listerLesDemandesAnnulation(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'demandes-annulation'] })

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.demandesAnnulation')} />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.demandesAnnulation.tousLesEtats')}
          value={etat}
          onChange={(v) => {
            setEtat(v)
            reinitialiser()
          }}
          options={ETATS.map((e) => ({ value: e, label: t(`backoffice.demandesAnnulation.etats.${e}`) }))}
        />
      </Space>

      <Table<DemandeAnnulation>
        rowKey="id"
        loading={demandes.isPending}
        dataSource={demandes.data?.elements}
        pagination={propsPagination(demandes.data?.pagination.total)}
        locale={{ emptyText: <EtatVide titre={t('backoffice.demandesAnnulation.aucune')} /> }}
        columns={[
          { title: t('backoffice.ticketsAssistance.sejour'), dataIndex: ['sejour', 'reference'] },
          { title: t('backoffice.reservations.client'), dataIndex: 'client' },
          { title: t('client.sejours.arrivee'), dataIndex: ['sejour', 'arrivee'], render: (v: string) => formaterDate(v) },
          { title: t('backoffice.demandesAnnulation.motifClient'), dataIndex: 'motif_client', ellipsis: true },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'etat',
            render: (_, d) => <StatutBadge domaine="demandeAnnulation" code={d.etat} libelle={d.etat_libelle} />,
          },
          {
            title: t('backoffice.demandesAnnulation.montantRembourse'),
            dataIndex: 'montant_rembourse',
            render: (v: number | null) => (v !== null ? formaterPrix(v) : <Typography.Text type="secondary">—</Typography.Text>),
          },
          {
            title: '',
            key: 'actions',
            render: (_, d) =>
              d.etat === 'en_attente' ? (
                <Space size={4}>
                  <Button size="small" type="primary" onClick={() => setDecision({ demande: d, type: 'acceptation' })}>
                    {t('backoffice.demandesAnnulation.accepter')}
                  </Button>
                  <Button size="small" danger onClick={() => setDecision({ demande: d, type: 'rejet' })}>
                    {t('backoffice.demandesAnnulation.rejeter')}
                  </Button>
                </Space>
              ) : null,
          },
        ]}
      />

      <FormulaireDecisionAnnulation
        demande={decision?.demande ?? null}
        decision={decision?.type ?? null}
        onFermer={() => setDecision(null)}
        onDecide={() => {
          setDecision(null)
          void invalider()
        }}
      />
    </div>
  )
}
