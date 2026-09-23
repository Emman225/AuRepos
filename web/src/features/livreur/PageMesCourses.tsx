import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { Modal } from '../../shared/composants/PopupModal'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { formaterPrix } from '../../shared/format/devise'
import { cloturerUneCourse, mesCourses } from './api'
import type { Commande } from './types'

/** Espace livreur › SES courses : liste, clôture par code (CdC — espace livreur). Le code n'est jamais lu ici, seulement saisi. */
export function PageMesCourses() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [courseACloturer, setCourseACloturer] = useState<Commande | null>(null)

  const courses = useQuery({ queryKey: ['livreur', 'courses'], queryFn: mesCourses })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['livreur', 'courses'] })

  return (
    <div>
      <EnTeteDePage titre={t('livreur.menu.courses')} />

      <Table<Commande>
        rowKey="id"
        loading={courses.isPending}
        dataSource={courses.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('livreur.courses.aucune')} /> }}
        columns={[
          { title: t('backoffice.commandesRepas.reference'), dataIndex: 'reference' },
          { title: t('backoffice.commandesRepas.restaurateur'), dataIndex: 'restaurateur', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.commandesRepas.montant'), dataIndex: 'montant_total', render: (v: number) => formaterPrix(v) },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'etat',
            render: (v: Commande['etat'], c) => <StatutBadge domaine="commandeRepas" code={v} libelle={c.etat_libelle} />,
          },
          {
            title: '',
            key: 'actions',
            render: (_, c) =>
              c.etat === 'en_livraison' && (
                <Button size="small" type="primary" onClick={() => setCourseACloturer(c)}>
                  {t('livreur.courses.cloturer')}
                </Button>
              ),
          },
        ]}
      />

      <ModaleCloture
        course={courseACloturer}
        onFermer={() => setCourseACloturer(null)}
        onCloturee={() => {
          setCourseACloturer(null)
          void invalider()
        }}
      />
    </div>
  )
}

function ModaleCloture({ course, onFermer, onCloturee }: { course: Commande | null; onFermer: () => void; onCloturee: () => void }) {
  const { t } = useTranslation()
  const [code, setCode] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const cloturer = useMutation({
    mutationFn: () => cloturerUneCourse(course!.id, code),
    onSuccess: () => {
      setCode('')
      setErreur(null)
      onCloturee()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('livreur.courses.cloturer')}
      open={course !== null}
      onCancel={() => {
        setCode('')
        setErreur(null)
        onFermer()
      }}
      onOk={() => cloturer.mutate()}
      confirmLoading={cloturer.isPending}
      okButtonProps={{ disabled: !code.trim() }}
      okText={t('livreur.courses.cloturer')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
    >
      <Typography.Paragraph type="secondary">{t('livreur.courses.codeAide')}</Typography.Paragraph>
      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text>{t('livreur.courses.code')}</Typography.Text>
        <Input value={code} onChange={(e) => setCode(e.target.value)} aria-label={t('livreur.courses.code')} autoFocus />
        {erreur && <Alert type="error" showIcon title={erreur} />}
      </Space>
    </Modal>
  )
}
