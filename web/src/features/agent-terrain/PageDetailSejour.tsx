import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Col, Descriptions, Row, Skeleton, Space, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { EtatErreur } from '../../shared/composants/EtatErreur'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { formaterDate } from '../../shared/format/date'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { afficherUnSejour } from './api'
import { FormulaireCheckIn } from './FormulaireCheckIn'
import { FormulaireCheckOut } from './FormulaireCheckOut'
import { SectionEtatsDesLieux } from './SectionEtatsDesLieux'
import { SectionOccupants } from './SectionOccupants'
import type { SejourAgent } from './types'

/**
 * Espace agent de terrain › Détail d'un séjour (CdC § 6.3, P2-MOB-04) : check-in, fiche de
 * police, état des lieux, check-out — mêmes actions que la fiche back office
 * (PageDetailReservation.tsx), sans la confirmation de réservation ni la prolongation (hors du
 * périmètre de routes/api_v1/agent.php, réservées au back office).
 */
export function PageDetailSejour() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const idNombre = Number(id)
  const queryClient = useQueryClient()
  const [checkInOuvert, setCheckInOuvert] = useState(false)
  const [checkOutOuvert, setCheckOutOuvert] = useState(false)

  const sejour = useQuery({
    queryKey: ['agent', 'sejours', idNombre],
    queryFn: () => afficherUnSejour(idNombre),
    enabled: Number.isFinite(idNombre),
  })

  if (sejour.isPending) return <Skeleton active paragraph={{ rows: 8 }} />
  if (!sejour.data) return <EtatErreur titre={t('client.sejours.introuvable')} />

  const s = sejour.data

  const surCycleDeVie = (mis_a_jour: SejourAgent) => {
    queryClient.setQueryData(['agent', 'sejours', idNombre], mis_a_jour)
    setCheckInOuvert(false)
    setCheckOutOuvert(false)
  }

  return (
    <div>
      <Link to=".." style={{ fontSize: 13 }}>
        {t('client.detail.retourALaListe')}
      </Link>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 12, margin: '10px 0 24px' }}>
        <div>
          <Space align="center" size={12}>
            <Typography.Title level={3} style={{ margin: 0 }}>
              {s.reference}
            </Typography.Title>
            <StatutBadge domaine="sejour" code={s.etat} libelle={s.etat_libelle} />
          </Space>
          <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, marginTop: 4 }}>
            <Typography.Text strong>{s.logement.nom}</Typography.Text> — {s.logement.residence}
          </Typography.Text>
        </div>
        <div style={{ textAlign: 'right' }}>
          <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
            {t('client.sejours.netAPayer')}
          </Typography.Text>
          <Typography.Text strong style={{ fontSize: 24, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
            {formaterPrix(s.net_a_payer)}
          </Typography.Text>
        </div>
      </div>

      {s.etat === 'no_show' && s.no_show_le && (
        <Alert style={{ marginBottom: 16 }} type="warning" showIcon title={t('backoffice.reservations.detail.noShow', { date: s.no_show_le })} />
      )}

      {s.etat === 'parti' && s.caution_retenue !== null && s.caution_retenue > 0 && (
        <Alert
          style={{ marginBottom: 16 }}
          type="info"
          showIcon
          title={t('backoffice.reservations.detail.cautionRetenueInfo', { montant: formaterPrix(s.caution_retenue) })}
          description={s.caution_retenue_motif ?? undefined}
        />
      )}

      {(s.etat === 'confirme' || s.etat === 'arrive') && (
        <Space wrap style={{ marginBottom: 24 }}>
          {s.etat === 'confirme' && (
            <Button type="primary" onClick={() => setCheckInOuvert(true)}>
              {t('backoffice.reservations.checkIn.action')}
            </Button>
          )}
          {s.etat === 'arrive' && (
            <Button type="primary" onClick={() => setCheckOutOuvert(true)}>
              {t('backoffice.reservations.checkOut.action')}
            </Button>
          )}
        </Space>
      )}

      <Row gutter={32}>
        <Col xs={24} md={14}>
          <Typography.Title level={5}>{t('backoffice.reservations.detail.sejour')}</Typography.Title>
          <Descriptions bordered size="small" column={1} style={{ marginBottom: 24 }}>
            <Descriptions.Item label={`${t('tunnel.arrivee')} → ${t('tunnel.depart')}`}>
              {`${formaterDate(s.arrivee)} → ${formaterDate(s.depart)} (${t('client.detail.nuits', { count: s.nombre_de_nuits })})`}
            </Descriptions.Item>
            <Descriptions.Item label={t('backoffice.reservations.detail.occupants')}>
              {`${t('backoffice.reservations.detail.occupantsAdultes', { count: s.adultes })}${s.enfants > 0 ? t('backoffice.reservations.detail.etEnfants', { count: s.enfants }) : ''}`}
            </Descriptions.Item>
            {s.client && (
              <Descriptions.Item label={t('backoffice.reservations.client')}>
                {`${s.client.nom}${s.client.telephone ? ` — ${s.client.telephone}` : ''}`}
              </Descriptions.Item>
            )}
          </Descriptions>
        </Col>

        <Col xs={24} md={10}>
          <Typography.Title level={5}>{t('backoffice.reservations.detail.paiement')}</Typography.Title>
          <Descriptions bordered size="small" column={1}>
            <Descriptions.Item label={t('backoffice.reservations.detail.encaisse')}>{formaterPrix(s.reglement.encaisse)}</Descriptions.Item>
            <Descriptions.Item label={t('backoffice.reservations.detail.resteDu')}>{formaterPrix(s.reglement.reste_du)}</Descriptions.Item>
            <Descriptions.Item label={t('backoffice.reservations.detail.caution')}>{formaterPrix(s.caution)}</Descriptions.Item>
            {s.caution_retenue !== null && (
              <Descriptions.Item label={t('backoffice.reservations.detail.cautionRetenue')}>{formaterPrix(s.caution_retenue)}</Descriptions.Item>
            )}
          </Descriptions>
        </Col>
      </Row>

      {!['demande', 'annule'].includes(s.etat) && (
        <>
          <SectionOccupants sejourId={s.id} />
          <SectionEtatsDesLieux sejourId={s.id} />
        </>
      )}

      <FormulaireCheckIn ouvert={checkInOuvert} sejour={s} onFermer={() => setCheckInOuvert(false)} onCheckIn={surCycleDeVie} />
      <FormulaireCheckOut ouvert={checkOutOuvert} sejour={s} onFermer={() => setCheckOutOuvert(false)} onCheckOut={surCycleDeVie} />
    </div>
  )
}
