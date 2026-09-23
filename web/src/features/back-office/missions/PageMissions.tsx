import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterDate } from '../../../shared/format/date'
import { listerLesMissions } from './api'
import { FormulaireAffectationMission } from './FormulaireAffectationMission'
import { FormulaireDemandeMenage } from './FormulaireDemandeMenage'
import type { Mission, StatutDeMission } from './types'

const STATUTS: StatutDeMission[] = ['a_faire', 'en_cours', 'faite']

/** Back office › Missions de ménage (P2-MEN-01, CdC § 6.4) : consultation, affectation à un agent de terrain, ménage demandé. */
export function PageMissions() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<StatutDeMission | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [missionAAffecter, setMissionAAffecter] = useState<Mission | null>(null)
  const [demandeOuverte, setDemandeOuverte] = useState(false)

  const filtres = { statut, page, par_page: parPage }
  const missions = useQuery({ queryKey: ['backoffice', 'missions', filtres], queryFn: () => listerLesMissions(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'missions'] })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.missions')}
        actions={<Button type="primary" onClick={() => setDemandeOuverte(true)}>{t('backoffice.missions.demande.action')}</Button>}
      />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.missions.tousLesStatuts')}
          value={statut}
          onChange={(v) => {
            setStatut(v)
            reinitialiser()
          }}
          options={STATUTS.map((s) => ({ value: s, label: t(`backoffice.missions.statuts.${s}`) }))}
        />
      </Space>

      <Table<Mission>
        rowKey="id"
        loading={missions.isPending}
        dataSource={missions.data?.elements}
        pagination={propsPagination(missions.data?.pagination.total)}
        locale={{ emptyText: <EtatVide titre={t('backoffice.missions.aucune')} /> }}
        columns={[
          { title: t('backoffice.missions.type'), dataIndex: 'type_libelle' },
          {
            title: t('backoffice.missions.logement'),
            render: (_, m) => (m.logement ? `${m.logement.nom}${m.logement.residence ? ` — ${m.logement.residence}` : ''}` : '—'),
          },
          { title: t('backoffice.missions.sejour'), render: (_, m) => m.sejour?.reference ?? '—' },
          { title: t('backoffice.missions.echeance'), dataIndex: 'echeance', render: (v: string) => formaterDate(v) },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'statut',
            render: (_, m) => <StatutBadge domaine="mission" code={m.statut} libelle={m.statut_libelle} />,
          },
          { title: t('backoffice.missions.agent'), dataIndex: 'agent', render: (v: string | null) => v ?? <Typography.Text type="secondary">{t('backoffice.missions.nonAffectee')}</Typography.Text> },
          {
            title: '',
            key: 'actions',
            render: (_, m) =>
              m.statut !== 'faite' ? (
                <Button size="small" onClick={() => setMissionAAffecter(m)}>
                  {t('backoffice.missions.affecter.action')}
                </Button>
              ) : null,
          },
        ]}
      />

      <FormulaireAffectationMission
        mission={missionAAffecter}
        onFermer={() => setMissionAAffecter(null)}
        onAffectee={() => {
          setMissionAAffecter(null)
          void invalider()
        }}
      />
      <FormulaireDemandeMenage
        ouvert={demandeOuverte}
        onFermer={() => setDemandeOuverte(false)}
        onDemande={() => {
          setDemandeOuverte(false)
          void invalider()
        }}
      />
    </div>
  )
}
