import { useQuery } from '@tanstack/react-query'
import { Button, Card, Col, Row, Skeleton, Statistic, Table, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { formaterPrix } from '../../shared/format/devise'
import type { Reglement } from './types'
import { mesPaiements, ouvrirMonRecu } from './api'

/** Espace client › Mes paiements et reçus (CdC § 5.3) : avance disponible, reste à régler en agence. */
export function PageMesPaiements() {
  const { t } = useTranslation()
  const paiements = useQuery({ queryKey: ['client', 'paiements'], queryFn: mesPaiements })

  if (paiements.isPending) return <Skeleton active paragraph={{ rows: 6 }} />

  return (
    <div>
      <Typography.Title level={3}>{t('client.menu.paiements')}</Typography.Title>
      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title={t('client.paiements.avanceDisponible')}
              value={formaterPrix(paiements.data?.avance_disponible ?? 0)}
            />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title={t('client.paiements.aReglerEnAgence')}
              value={formaterPrix(paiements.data?.montant_a_regler_en_agence ?? 0)}
            />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title={t('client.paiements.pointsDeFidelite')}
              value={paiements.data?.fidelite.solde ?? 0}
              suffix={`(${formaterPrix(paiements.data?.fidelite.valeur ?? 0)})`}
            />
          </Card>
        </Col>
      </Row>

      <Typography.Title level={4} style={{ marginTop: 32 }}>
        {t('client.paiements.mesReglements')}
      </Typography.Title>
      <Table<Reglement>
        rowKey="reference"
        dataSource={paiements.data?.reglements}
        pagination={false}
        columns={[
          { title: t('client.paiements.date'), dataIndex: 'date' },
          { title: t('client.paiements.montant'), dataIndex: 'montant', render: (v: number) => formaterPrix(v) },
          { title: t('client.paiements.mode'), dataIndex: 'mode' },
          { title: t('client.paiements.affaires'), dataIndex: 'affaires', render: (v: string[]) => v.join(', ') },
          {
            title: t('client.paiements.recu'),
            dataIndex: 'numero_recu',
            render: (v: string | null) =>
              v ? (
                <Button size="small" onClick={() => void ouvrirMonRecu(v)}>
                  {v}
                </Button>
              ) : null,
          },
        ]}
      />
    </div>
  )
}
