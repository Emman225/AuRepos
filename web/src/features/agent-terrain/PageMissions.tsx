import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { Modal } from '../../shared/composants/PopupModal'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { formaterDate } from '../../shared/format/date'
import { demarrerUneMission, mesMissions, terminerUneMission } from './api'
import type { Mission, StatutDeMission } from './types'

const STATUTS: StatutDeMission[] = ['a_faire', 'en_cours', 'faite']

/** Espace agent de terrain › Mes missions de ménage (CdC § 6.4, P2-MEN-01) : celles qui me sont affectées, démarrer / terminer. */
export function PageMissions() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<StatutDeMission | undefined>(undefined)
  const [missionATerminer, setMissionATerminer] = useState<Mission | null>(null)
  const [notes, setNotes] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const filtres = { statut }
  const missions = useQuery({ queryKey: ['agent', 'missions', filtres], queryFn: () => mesMissions(filtres) })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['agent', 'missions'] })

  const demarrer = useMutation({
    mutationFn: (id: number) => demarrerUneMission(id),
    onSuccess: () => void invalider(),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const terminer = useMutation({
    mutationFn: () => terminerUneMission(missionATerminer!.id, notes || undefined),
    onSuccess: () => {
      setMissionATerminer(null)
      setNotes('')
      setErreur(null)
      void invalider()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      <EnTeteDePage titre={t('agent.menu.missions')} />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.missions.tousLesStatuts')}
          value={statut}
          onChange={setStatut}
          options={STATUTS.map((s) => ({ value: s, label: t(`backoffice.missions.statuts.${s}`) }))}
        />
      </Space>

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} />}

      <Table<Mission>
        rowKey="id"
        loading={missions.isPending}
        dataSource={missions.data}
        pagination={false}
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
            render: (_, m) => <StatutBadge domaine="mission" code={m.statut} libelle={m.statut_libelle} />,
          },
          {
            title: '',
            key: 'actions',
            render: (_, m) =>
              m.statut === 'a_faire' ? (
                <Button size="small" loading={demarrer.isPending} onClick={() => demarrer.mutate(m.id)}>
                  {t('agent.missions.demarrer')}
                </Button>
              ) : m.statut === 'en_cours' ? (
                <Button
                  size="small"
                  type="primary"
                  onClick={() => {
                    setMissionATerminer(m)
                    setNotes('')
                    setErreur(null)
                  }}
                >
                  {t('agent.missions.terminer')}
                </Button>
              ) : null,
          },
        ]}
      />

      <Modal
        title={t('agent.missions.terminerTitre')}
        open={missionATerminer !== null}
        onCancel={() => setMissionATerminer(null)}
        onOk={() => terminer.mutate()}
        confirmLoading={terminer.isPending}
        okText={t('agent.missions.terminer')}
        cancelText={t('listes.confirmation.annuler')}
        destroyOnHidden
      >
        <Typography.Text>{t('agent.missions.notes')}</Typography.Text>
        <Input.TextArea
          style={{ marginTop: 4 }}
          rows={3}
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          aria-label={t('agent.missions.notes')}
        />
      </Modal>
    </div>
  )
}
