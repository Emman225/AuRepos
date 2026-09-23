import { useQueryClient, useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Col, Input, InputNumber, Row, Skeleton, Space, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { formaterDate } from '../../../shared/format/date'
import { formaterPrix } from '../../../shared/format/devise'
import { couleurs, tonsSemantiques } from '../../../shared/theme/jetons'
import { ResumeDevis } from '../../reservation/ResumeDevis'
import { afficherLaReservation, confirmerLaReservation, proposerUneReduction } from './api'
import { FormulaireCheckIn } from './FormulaireCheckIn'
import { FormulaireCheckOut } from './FormulaireCheckOut'
import { FormulaireProlongation } from './FormulaireProlongation'
import { SectionEtatsDesLieux } from './SectionEtatsDesLieux'
import { SectionOccupants } from './SectionOccupants'
import type { ReservationBackOffice } from './types'

const LIBELLE_CANAL: Record<string, string> = {
  telephone: 'backoffice.reservations.manuelle.canalTelephone',
  walk_in: 'backoffice.reservations.manuelle.canalWalkIn',
  canal_externe: 'backoffice.reservations.manuelle.canalExterne',
  direct: 'backoffice.reservations.detail.canalDirect',
}

const LIBELLE_MODE_REGLEMENT: Record<string, string> = {
  en_ligne: 'tunnel.reglement.enLigne',
  agence: 'tunnel.reglement.agence',
  a_terme: 'tunnel.reglement.aTerme',
}

function LigneSynthese({ libelle, valeur, accent }: { libelle: string; valeur: string; accent?: boolean }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', padding: '7px 0', borderBottom: `1px solid ${couleurs.bordure}` }}>
      <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret }}>{libelle}</Typography.Text>
      <Typography.Text strong style={{ fontSize: 13, color: accent ? tonsSemantiques.alerte.texte : couleurs.texte, fontVariantNumeric: 'tabular-nums' }}>
        {valeur}
      </Typography.Text>
    </div>
  )
}

/** Back office › Détail d'une réservation (CdC § 6.1 ; brief refonte §11) : synthèse critique en un coup d'œil, actions au premier plan. */
export function PageDetailReservation() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const idNombre = Number(id)
  const queryClient = useQueryClient()
  const [erreur, setErreur] = useState<string | null>(null)
  const [reductionOuverte, setReductionOuverte] = useState(false)
  const [pourcentage, setPourcentage] = useState(10)
  const [motif, setMotif] = useState('')
  const [messageReduction, setMessageReduction] = useState<string | null>(null)
  const [checkInOuvert, setCheckInOuvert] = useState(false)
  const [checkOutOuvert, setCheckOutOuvert] = useState(false)
  const [prolongationOuverte, setProlongationOuverte] = useState(false)

  const reservation = useQuery({
    queryKey: ['backoffice', 'reservations', idNombre],
    queryFn: () => afficherLaReservation(idNombre),
    enabled: Number.isFinite(idNombre),
  })

  const confirmation = useMutation({
    mutationFn: () => confirmerLaReservation(idNombre),
    onSuccess: (donnees) => queryClient.setQueryData(['backoffice', 'reservations', idNombre], donnees),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const reduction = useMutation({
    mutationFn: () => proposerUneReduction(idNombre, pourcentage, motif),
    onSuccess: () => {
      setReductionOuverte(false)
      setMessageReduction(t('backoffice.reservations.detail.reductionProposeeMessage'))
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  if (reservation.isPending) return <Skeleton active paragraph={{ rows: 8 }} />
  if (!reservation.data) return <Alert type="error" showIcon title={t('client.sejours.introuvable')} />

  const r = reservation.data

  const surCycleDeVie = (mise_a_jour: ReservationBackOffice) => {
    queryClient.setQueryData(['backoffice', 'reservations', idNombre], mise_a_jour)
    setCheckInOuvert(false)
    setCheckOutOuvert(false)
    setProlongationOuverte(false)
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
              {r.reference}
            </Typography.Title>
            <StatutBadge domaine="sejour" code={r.etat} libelle={r.etat_libelle} />
          </Space>
          <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, marginTop: 4 }}>
            <Typography.Text strong>{r.logement.nom}</Typography.Text> — {r.logement.residence}
          </Typography.Text>
        </div>
        <div style={{ textAlign: 'right' }}>
          <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
            {t('client.sejours.netAPayer')}
          </Typography.Text>
          <Typography.Text strong style={{ fontSize: 24, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
            {formaterPrix(r.net_a_payer)}
          </Typography.Text>
        </div>
      </div>

      {messageReduction && <Alert style={{ marginBottom: 16 }} type="success" showIcon title={messageReduction} />}
      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} />}

      {r.etat === 'demande' && r.obstacles_a_la_confirmation.length > 0 && (
        <Alert
          style={{ marginBottom: 16 }}
          type="warning"
          showIcon
          title={t('backoffice.reservations.detail.obstacles')}
          description={
            <ul style={{ margin: 0, paddingInlineStart: 20 }}>
              {r.obstacles_a_la_confirmation.map((o) => (
                <li key={o}>{o}</li>
              ))}
            </ul>
          }
        />
      )}

      {r.etat === 'demande' && (
        <Space wrap style={{ marginBottom: 24 }}>
          <Button
            type="primary"
            loading={confirmation.isPending}
            disabled={r.obstacles_a_la_confirmation.length > 0}
            onClick={() => confirmation.mutate()}
          >
            {t('backoffice.reservations.detail.confirmer')}
          </Button>
          <Button onClick={() => setReductionOuverte(true)}>{t('backoffice.reservations.detail.proposerUneReduction')}</Button>
        </Space>
      )}

      {(r.etat === 'confirme' || r.etat === 'arrive') && (
        <Space wrap style={{ marginBottom: 24 }}>
          {r.etat === 'confirme' && (
            <Button type="primary" onClick={() => setCheckInOuvert(true)}>
              {t('backoffice.reservations.checkIn.action')}
            </Button>
          )}
          {r.etat === 'arrive' && (
            <Button type="primary" onClick={() => setCheckOutOuvert(true)}>
              {t('backoffice.reservations.checkOut.action')}
            </Button>
          )}
          <Button onClick={() => setProlongationOuverte(true)}>{t('backoffice.reservations.prolongation.action')}</Button>
        </Space>
      )}

      {r.etat === 'no_show' && r.no_show_le && (
        <Alert
          style={{ marginBottom: 16 }}
          type="warning"
          showIcon
          title={t('backoffice.reservations.detail.noShow', { date: r.no_show_le })}
        />
      )}

      {r.etat === 'parti' && r.caution_retenue !== null && r.caution_retenue > 0 && (
        <Alert
          style={{ marginBottom: 16 }}
          type="info"
          showIcon
          title={t('backoffice.reservations.detail.cautionRetenueInfo', { montant: formaterPrix(r.caution_retenue) })}
          description={r.caution_retenue_motif ?? undefined}
        />
      )}

      <Row gutter={32}>
        <Col xs={24} md={14}>
          <Typography.Title level={5}>{t('backoffice.reservations.detail.sejour')}</Typography.Title>
          <div style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 10, padding: '4px 18px', marginBottom: 24 }}>
            <LigneSynthese
              libelle={`${t('tunnel.arrivee')} → ${t('tunnel.depart')}`}
              valeur={`${formaterDate(r.arrivee)} → ${formaterDate(r.depart)} (${t('client.detail.nuits', { count: r.nombre_de_nuits })})`}
            />
            <LigneSynthese
              libelle={t('backoffice.reservations.detail.occupants')}
              valeur={`${t('backoffice.reservations.detail.occupantsAdultes', { count: r.adultes })}${r.enfants > 0 ? t('backoffice.reservations.detail.etEnfants', { count: r.enfants }) : ''}`}
            />
            <LigneSynthese libelle={t('backoffice.reservations.detail.canal')} valeur={t(LIBELLE_CANAL[r.canal] ?? r.canal)} />
            {r.bon_de_commande && <LigneSynthese libelle={t('backoffice.reservations.detail.bonDeCommande')} valeur={r.bon_de_commande} />}
            {r.client && (
              <LigneSynthese
                libelle={t('backoffice.reservations.client')}
                valeur={`${r.client.nom}${r.client.telephone ? ` — ${r.client.telephone}` : ''}`}
              />
            )}
          </div>

          {r.devis && <ResumeDevis devis={r.devis} />}
        </Col>

        <Col xs={24} md={10}>
          <div style={{ position: 'sticky', top: 20 }}>
            <Typography.Title level={5}>{t('backoffice.reservations.detail.paiement')}</Typography.Title>
            <div style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 10, padding: '4px 18px' }}>
              <LigneSynthese libelle={t('backoffice.reservations.detail.encaisse')} valeur={formaterPrix(r.reglement.encaisse)} />
              <LigneSynthese
                libelle={t('backoffice.reservations.detail.resteDu')}
                valeur={formaterPrix(r.reglement.reste_du)}
                accent={r.reglement.reste_du > 0}
              />
              <LigneSynthese libelle={t('backoffice.reservations.detail.modeDeReglement')} valeur={t(LIBELLE_MODE_REGLEMENT[r.mode_reglement] ?? r.mode_reglement)} />
              <LigneSynthese libelle={t('backoffice.reservations.detail.acompteExige')} valeur={formaterPrix(r.acompte_exige)} />
              <LigneSynthese libelle={t('backoffice.reservations.detail.caution')} valeur={formaterPrix(r.caution)} />
              {r.caution_retenue !== null && (
                <LigneSynthese libelle={t('backoffice.reservations.detail.cautionRetenue')} valeur={formaterPrix(r.caution_retenue)} accent={r.caution_retenue > 0} />
              )}
            </div>
          </div>
        </Col>
      </Row>

      {!['demande', 'annule'].includes(r.etat) && (
        <>
          <SectionOccupants sejourId={r.id} />
          <SectionEtatsDesLieux sejourId={r.id} />
        </>
      )}

      <FormulaireCheckIn ouvert={checkInOuvert} sejour={r} onFermer={() => setCheckInOuvert(false)} onCheckIn={surCycleDeVie} />
      <FormulaireCheckOut ouvert={checkOutOuvert} sejour={r} onFermer={() => setCheckOutOuvert(false)} onCheckOut={surCycleDeVie} />
      <FormulaireProlongation ouvert={prolongationOuverte} sejour={r} onFermer={() => setProlongationOuverte(false)} onModifie={surCycleDeVie} />

      <Modal
        title={t('backoffice.reservations.detail.proposerUneReduction')}
        open={reductionOuverte}
        onCancel={() => setReductionOuverte(false)}
        onOk={() => reduction.mutate()}
        confirmLoading={reduction.isPending}
        okText={t('backoffice.reservations.manuelle.enregistrer')}
        cancelText={t('listes.confirmation.annuler')}
      >
        <Typography.Paragraph type="secondary">{t('backoffice.reservations.detail.reductionAide')}</Typography.Paragraph>
        <Typography.Text>{t('backoffice.reservations.detail.pourcentage')}</Typography.Text>
        <InputNumber style={{ width: '100%', marginTop: 4, marginBottom: 12 }} min={0} max={100} value={pourcentage} onChange={(v) => setPourcentage(v ?? 0)} />
        <Typography.Text>{t('backoffice.reservations.detail.motif')}</Typography.Text>
        <Input.TextArea
          style={{ marginTop: 4 }}
          rows={3}
          value={motif}
          onChange={(e) => setMotif(e.target.value)}
          aria-label={t('backoffice.reservations.detail.motif')}
        />
      </Modal>
    </div>
  )
}
