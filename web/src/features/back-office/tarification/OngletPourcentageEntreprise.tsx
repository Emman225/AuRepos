import { useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Card, Input, InputNumber, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { listerLesDerogations, proposerLePourcentageEntrepriseGlobal } from './api'
import type { Derogation } from './types'

/** Pourcentage entreprise global (double validation) et dérogations par logement (CdC § 7.3). */
export function OngletPourcentageEntreprise() {
  const { t } = useTranslation()
  const [taux, setTaux] = useState<number | null>(null)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const derogations = useQuery({ queryKey: ['backoffice', 'tarification', 'derogations'], queryFn: listerLesDerogations })

  const proposer = useMutation({
    mutationFn: () => proposerLePourcentageEntrepriseGlobal(taux ?? 0, motif || undefined),
    onSuccess: (changement) => {
      setMessage(t('backoffice.tarification.pourcentage.propositionEnvoyee', { montant: changement.valeur_proposee ?? '' }))
      setErreur(null)
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      <Card title={t('backoffice.tarification.pourcentage.titre')} style={{ marginBottom: 16 }}>
        <Typography.Paragraph type="secondary">{t('backoffice.tarification.pourcentage.aide')}</Typography.Paragraph>
        <Space wrap>
          <InputNumber min={0} max={500} value={taux} onChange={setTaux} addonAfter="%" />
          <Input placeholder={t('backoffice.catalogue.prix.motif')} value={motif} onChange={(e) => setMotif(e.target.value)} style={{ width: 240 }} />
          <Button type="primary" loading={proposer.isPending} disabled={taux === null} onClick={() => proposer.mutate()}>
            {t('backoffice.tarification.pourcentage.proposer')}
          </Button>
        </Space>
        {message && <Alert style={{ marginTop: 16 }} type="success" showIcon title={message} />}
        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Card>

      <Card title={t('backoffice.tarification.pourcentage.derogations')}>
        <Table<Derogation>
          rowKey="logement_id"
          size="small"
          loading={derogations.isPending}
          dataSource={derogations.data?.derogations}
          pagination={false}
          columns={[
            { title: t('backoffice.catalogue.logement.reference'), dataIndex: 'reference' },
            { title: t('backoffice.catalogue.logement.nom'), dataIndex: 'nom' },
            { title: t('backoffice.catalogue.residence.nom'), dataIndex: 'residence' },
            { title: t('backoffice.tarification.pourcentage.tauxGlobal'), dataIndex: 'taux_global', render: (v: number) => `${v} %` },
            { title: t('backoffice.tarification.pourcentage.tauxDerogation'), dataIndex: 'taux_derogation', render: (v: number | null) => (v !== null ? `${v} %` : '—') },
          ]}
        />
      </Card>
    </div>
  )
}
