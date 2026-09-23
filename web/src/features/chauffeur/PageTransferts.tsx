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
import { cloturerUnTransfert, mesTransferts } from './api'
import type { TransfertChauffeur } from './types'

/** Espace chauffeur › Mes transferts (CdC § 6.6) : SES transferts affectés, clôture par le code communiqué par le client (jamais lu ici, § 11). */
export function PageTransferts() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [transfertACloturer, setTransfertACloturer] = useState<TransfertChauffeur | null>(null)

  const transferts = useQuery({ queryKey: ['chauffeur', 'transferts'], queryFn: mesTransferts })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['chauffeur', 'transferts'] })

  return (
    <div>
      <EnTeteDePage titre={t('chauffeur.menu.transferts')} />

      <Table<TransfertChauffeur>
        rowKey="reference"
        loading={transferts.isPending}
        dataSource={transferts.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('chauffeur.transferts.aucun')} /> }}
        columns={[
          { title: t('backoffice.transferts.reference'), dataIndex: 'reference' },
          { title: t('chauffeur.transferts.lieu'), dataIndex: 'lieu_de_prise_en_charge' },
          { title: t('chauffeur.transferts.commune'), dataIndex: 'commune', render: (v: string | null) => v ?? '—' },
          { title: t('chauffeur.transferts.dateHeure'), dataIndex: 'date_heure_prevue' },
          { title: t('chauffeur.transferts.vehicule'), dataIndex: 'vehicule', render: (v: string | null) => v ?? '—' },
          {
            title: t('chauffeur.transferts.montant'),
            dataIndex: 'montant_verse_au_chauffeur',
            render: (v: number | null) => (v === null ? '—' : formaterPrix(v)),
          },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'etat',
            render: (_, r) => <StatutBadge domaine="transfert" code={r.etat} libelle={r.etat_libelle} />,
          },
          {
            title: '',
            key: 'actions',
            render: (_, r) =>
              r.etat === 'affecte' ? (
                <Button size="small" type="primary" onClick={() => setTransfertACloturer(r)}>
                  {t('chauffeur.transferts.cloturer')}
                </Button>
              ) : null,
          },
        ]}
      />

      <FormulaireCloture
        transfert={transfertACloturer}
        onFermer={() => setTransfertACloturer(null)}
        onCloture={() => {
          setTransfertACloturer(null)
          void invalider()
        }}
      />
    </div>
  )
}

function FormulaireCloture({
  transfert,
  onFermer,
  onCloture,
}: {
  transfert: TransfertChauffeur | null
  onFermer: () => void
  onCloture: () => void
}) {
  const { t } = useTranslation()
  const [code, setCode] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const cloturer = useMutation({
    mutationFn: () => cloturerUnTransfert(transfert!.reference, code),
    onSuccess: () => {
      setCode('')
      onCloture()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('chauffeur.transferts.cloturer')}
      open={transfert !== null}
      onCancel={() => {
        setCode('')
        setErreur(null)
        onFermer()
      }}
      onOk={() => cloturer.mutate()}
      confirmLoading={cloturer.isPending}
      okText={t('chauffeur.transferts.cloturer')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
    >
      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text>{t('chauffeur.transferts.code')}</Typography.Text>
        <Input
          aria-label={t('chauffeur.transferts.code')}
          value={code}
          onChange={(e) => setCode(e.target.value)}
          size="large"
          autoFocus
        />
        <Typography.Text style={{ fontSize: 12 }} type="secondary">
          {t('chauffeur.transferts.codeAide')}
        </Typography.Text>
        {erreur && <Alert type="error" showIcon title={erreur} />}
      </Space>
    </Modal>
  )
}
