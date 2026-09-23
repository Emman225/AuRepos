import { useQuery } from '@tanstack/react-query'
import { Card, Col, Row, Skeleton, Statistic, Typography } from 'antd'
import dayjs from 'dayjs'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formaterDate } from '../../shared/format/date'
import { formaterPrix } from '../../shared/format/devise'
import { mesDevis, mesPaiements, mesSejours } from './api'

/** Espace client › Tableau de bord (CdC § 5.3) : un coup d'œil sur ce qui attend une action. */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const sejours = useQuery({ queryKey: ['client', 'sejours'], queryFn: mesSejours })
  const devis = useQuery({ queryKey: ['client', 'devis'], queryFn: mesDevis })
  const paiements = useQuery({ queryKey: ['client', 'paiements'], queryFn: mesPaiements })

  const chargement = sejours.isPending || devis.isPending || paiements.isPending
  const prochainSejour = sejours.data?.elements
    .filter((s) => s.etat !== 'annule' && !dayjs(s.depart).isBefore(dayjs(), 'day'))
    .sort((a, b) => dayjs(a.arrivee).diff(dayjs(b.arrivee)))[0]
  const devisEnAttente = devis.data?.elements.filter((d) => d.etat === 'en_attente').length ?? 0

  if (chargement) return <Skeleton active paragraph={{ rows: 6 }} />

  return (
    <div>
      <Typography.Title level={3}>{t('espace.tableauDeBord')}</Typography.Title>
      <Row gutter={[16, 16]}>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title={t('client.tableauDeBord.avanceDisponible')}
              value={formaterPrix(paiements.data?.avance_disponible ?? 0)}
            />
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic title={t('client.tableauDeBord.devisEnAttente')} value={devisEnAttente} />
            <Link to="devis">{t('client.tableauDeBord.voirMesDevis')}</Link>
          </Card>
        </Col>
        <Col xs={24} md={8}>
          <Card>
            <Statistic
              title={t('client.tableauDeBord.pointsDeFidelite')}
              value={paiements.data?.fidelite.solde ?? 0}
            />
          </Card>
        </Col>
      </Row>

      <Typography.Title level={4} style={{ marginTop: 32 }}>
        {t('client.tableauDeBord.prochainSejour')}
      </Typography.Title>
      {prochainSejour ? (
        <Card>
          <Typography.Paragraph strong>{prochainSejour.logement.nom}</Typography.Paragraph>
          <Typography.Paragraph>
            {formaterDate(prochainSejour.arrivee)} — {formaterDate(prochainSejour.depart)} ·{' '}
            {prochainSejour.etat_libelle}
          </Typography.Paragraph>
          <Link to={`sejours/${prochainSejour.reference}`}>{t('client.tableauDeBord.voirLeDetail')}</Link>
        </Card>
      ) : (
        <Typography.Text type="secondary">{t('client.tableauDeBord.aucunSejour')}</Typography.Text>
      )}
    </div>
  )
}
