import { useQuery } from '@tanstack/react-query'
import {
  Alert,
  Button,
  Checkbox,
  Col,
  DatePicker,
  Input,
  InputNumber,
  Radio,
  Result,
  Row,
  Skeleton,
  Space,
  Steps,
  Typography,
} from 'antd'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { ErreurApi, lire } from '../../shared/api/client'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { policeDisplay } from '../../shared/theme/jetonsSitePublic'
import type { LogementFiche } from '../site-public/types'
import { estimer, etablirUnDevis, initierLePaiement, monCompteATerme, reserver, transformerLeDevis } from './api'
import { ResumeDevis } from './ResumeDevis'
import type { ModeReglement, Sejour } from './types'

/** Les 3 phases réelles du tunnel (brief refonte §17) — pas de fausses étapes sans contenu : ici tout est déjà séquentiel, seulement pas visible comme tel. */
function EtapesDuTunnel({ etape }: { etape: 0 | 1 | 2 }) {
  const { t } = useTranslation()
  return (
    <Steps
      size="small"
      current={etape}
      style={{ marginBottom: 28, maxWidth: 480 }}
      items={[
        { title: t('tunnel.etapes.sejour') },
        { title: t('tunnel.etapes.reglement') },
        { title: t('tunnel.etapes.confirmation') },
      ]}
    />
  )
}

/** Tunnel de réservation (CdC § 5.1, 5.2) : total recalculé par le serveur pendant la saisie. */
export function PageTunnelReservation() {
  const { t } = useTranslation()
  const { reference } = useParams<{ reference: string }>()
  const [searchParams] = useSearchParams()

  const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(() => {
    const arrivee = searchParams.get('arrivee')
    const depart = searchParams.get('depart')
    return arrivee && depart ? [dayjs(arrivee), dayjs(depart)] : null
  })
  const [adultes, setAdultes] = useState(Number(searchParams.get('adultes') ?? 2))
  const [enfants, setEnfants] = useState(Number(searchParams.get('enfants') ?? 0))
  const [codePromo, setCodePromo] = useState('')
  const [utiliserLesPoints, setUtiliserLesPoints] = useState(false)
  const [modeReglement, setModeReglement] = useState<ModeReglement>('en_ligne')
  const [envoiEnCours, setEnvoiEnCours] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [resultat, setResultat] = useState<
    { type: 'sejour'; sejour: Sejour } | { type: 'devis'; reference: string } | null
  >(null)

  const fiche = useQuery({
    queryKey: ['catalogue', 'logement', reference],
    queryFn: () => lire<LogementFiche>(`/catalogue/logements/${reference}`),
    enabled: !!reference,
  })
  const compteATerme = useQuery({ queryKey: ['compte-a-terme'], queryFn: monCompteATerme })

  const arrivee = dates?.[0]?.format('YYYY-MM-DD')
  const depart = dates?.[1]?.format('YYYY-MM-DD')
  const estimation = useQuery({
    queryKey: ['estimation', reference, arrivee, depart, adultes, enfants, codePromo],
    queryFn: () =>
      estimer(reference ?? '', {
        arrivee: arrivee ?? '',
        depart: depart ?? '',
        adultes,
        enfants,
        code_promo: codePromo || undefined,
      }),
    enabled: !!reference && !!arrivee && !!depart,
  })

  if (resultat) {
    return <PageConfirmation resultat={resultat} />
  }

  const pointsUtilises = utiliserLesPoints ? (estimation.data?.fidelite?.utilisables ?? 0) : 0

  const soumettre = async (intention: 'reservation' | 'devis'): Promise<void> => {
    if (!reference || !arrivee || !depart) return
    setErreur(null)
    setEnvoiEnCours(true)
    try {
      if (intention === 'devis') {
        const devis = await etablirUnDevis({
          reference_logement: reference,
          arrivee,
          depart,
          adultes,
          enfants,
          code_promo: codePromo || undefined,
          points_utilises: pointsUtilises,
        })
        setResultat({ type: 'devis', reference: devis.reference })
      } else {
        const sejour = await reserver({
          reference_logement: reference,
          arrivee,
          depart,
          adultes,
          enfants,
          mode_reglement: modeReglement,
          code_promo: codePromo || undefined,
          points_utilises: pointsUtilises,
        })
        setResultat({ type: 'sejour', sejour })
      }
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    } finally {
      setEnvoiEnCours(false)
    }
  }

  const etapeActuelle = !arrivee || !depart ? 0 : 1

  return (
    <div style={{ padding: '48px 24px', maxWidth: 1080, margin: '0 auto', width: '100%' }}>
      <Typography.Title level={1} style={{ fontFamily: policeDisplay, fontWeight: 500, fontSize: 'clamp(26px, 3.4vw, 36px)' }}>
        {t('tunnel.titre')}
      </Typography.Title>
      {fiche.data && (
        <Typography.Paragraph style={{ color: couleurs.texteDiscret }}>
          <Link to={`/logements/${fiche.data.reference}`}>{fiche.data.nom}</Link> — {fiche.data.lieu.quartier},{' '}
          {fiche.data.lieu.commune}
        </Typography.Paragraph>
      )}

      <EtapesDuTunnel etape={etapeActuelle} />

      <Row gutter={40}>
        <Col xs={24} md={14}>
          <Row gutter={[12, 12]}>
            <Col xs={24} md={24}>
              <DatePicker.RangePicker
                style={{ width: '100%' }}
                value={dates}
                onChange={(v) => setDates(v && v[0] && v[1] ? [v[0], v[1]] : null)}
                disabledDate={(d) => d.isBefore(dayjs().startOf('day'))}
                format="DD/MM/YYYY"
                placeholder={[t('tunnel.arrivee'), t('tunnel.depart')]}
              />
            </Col>
            <Col xs={12} md={12}>
              <Typography.Text style={{ display: 'block', marginBottom: 4 }} type="secondary">
                {t('tunnel.adultes')}
              </Typography.Text>
              <InputNumber
                style={{ width: '100%' }}
                min={1}
                max={60}
                value={adultes}
                onChange={(v) => setAdultes(v ?? 1)}
              />
            </Col>
            <Col xs={12} md={12}>
              <Typography.Text style={{ display: 'block', marginBottom: 4 }} type="secondary">
                {t('tunnel.enfants')}
              </Typography.Text>
              <InputNumber
                style={{ width: '100%' }}
                min={0}
                max={60}
                value={enfants}
                onChange={(v) => setEnfants(v ?? 0)}
              />
            </Col>
            <Col xs={24}>
              <Input value={codePromo} onChange={(e) => setCodePromo(e.target.value)} placeholder={t('tunnel.codePromo')} />
            </Col>
          </Row>

          {estimation.data && estimation.data.disponible && (
            <div style={{ marginTop: 28 }}>
              {estimation.data.code_promo.motif && (
                <Alert style={{ marginBottom: 12 }} type="warning" showIcon title={estimation.data.code_promo.motif} />
              )}

              {estimation.data.fidelite && estimation.data.fidelite.utilisables > 0 && (
                <Checkbox
                  checked={utiliserLesPoints}
                  onChange={(e) => setUtiliserLesPoints(e.target.checked)}
                  style={{ marginBottom: 12, display: 'block' }}
                >
                  {t('tunnel.utiliserMesPoints', { valeur: formaterPrix(estimation.data.fidelite.valeur) })}
                </Checkbox>
              )}

              <Typography.Title level={4} style={{ marginTop: 12 }}>
                {t('tunnel.reglement.titre')}
              </Typography.Title>
              <Radio.Group value={modeReglement} onChange={(e) => setModeReglement(e.target.value as ModeReglement)}>
                <Space orientation="vertical">
                  <Radio value="en_ligne">{t('tunnel.reglement.enLigne')}</Radio>
                  <Radio value="agence">{t('tunnel.reglement.agence')}</Radio>
                  {compteATerme.data?.statut === 'acceptee' && (
                    <Radio value="a_terme">{t('tunnel.reglement.aTerme')}</Radio>
                  )}
                </Space>
              </Radio.Group>

              {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}

              <Space style={{ marginTop: 24 }}>
                <Button
                  type="primary"
                  size="large"
                  loading={envoiEnCours}
                  onClick={() => void soumettre('reservation')}
                >
                  {t('tunnel.reserver')}
                </Button>
                <Button size="large" loading={envoiEnCours} onClick={() => void soumettre('devis')}>
                  {t('tunnel.obtenirUnDevis')}
                </Button>
              </Space>
            </div>
          )}
        </Col>

        <Col xs={24} md={10}>
          <div style={{ position: 'sticky', top: 20 }}>
            {!arrivee || !depart ? (
              <div style={{ border: `1px dashed ${couleurs.bordure}`, borderRadius: 10, padding: 24, textAlign: 'center' }}>
                <Typography.Text type="secondary">{t('tunnel.choisirLesDates')}</Typography.Text>
              </div>
            ) : estimation.isPending ? (
              <Skeleton active paragraph={{ rows: 4 }} />
            ) : estimation.isError ? (
              <Alert
                type="error"
                showIcon
                title={estimation.error instanceof ErreurApi ? estimation.error.message : t('tunnel.erreurGenerique')}
              />
            ) : estimation.data && !estimation.data.disponible ? (
              <Alert type="warning" showIcon title={t('tunnel.datesIndisponibles')} />
            ) : (
              estimation.data && <ResumeDevis devis={estimation.data} />
            )}
          </div>
        </Col>
      </Row>
    </div>
  )
}

export function PageConfirmation({
  resultat,
}: {
  resultat: { type: 'sejour'; sejour: Sejour } | { type: 'devis'; reference: string }
}) {
  const { t } = useTranslation()
  const [initiation, setInitiation] = useState<'attente' | 'en_cours' | 'erreur'>('attente')
  const [erreur, setErreur] = useState<string | null>(null)

  const payerMaintenant = async (referenceSejour: string): Promise<void> => {
    setInitiation('en_cours')
    try {
      const paiement = await initierLePaiement(referenceSejour)
      window.location.href = paiement.url_paiement
    } catch (e) {
      setInitiation('erreur')
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    }
  }

  if (resultat.type === 'devis') {
    return (
      <div style={{ padding: '48px 24px', maxWidth: 700, margin: '0 auto' }}>
        <EtapesDuTunnel etape={2} />
        <Result
          status="success"
          title={t('tunnel.devisEtabli.titre')}
          subTitle={t('tunnel.devisEtabli.sousTitre', { reference: resultat.reference })}
        />
        <SectionTransformationDevis reference={resultat.reference} />
      </div>
    )
  }

  const { sejour } = resultat
  return (
    <div style={{ padding: '48px 24px', maxWidth: 700, margin: '0 auto' }}>
      <EtapesDuTunnel etape={2} />
      <Result
        status="success"
        title={t('tunnel.confirmation.titre')}
        subTitle={t('tunnel.confirmation.sousTitre', { reference: sejour.reference })}
      />
      <div style={{ background: couleurs.sableClair, borderRadius: 8, padding: 20 }}>
        <Typography.Paragraph>
          <strong>{t('tunnel.confirmation.netAPayer')}</strong> {formaterPrix(sejour.net_a_payer)}
        </Typography.Paragraph>
        {sejour.expire_le && (
          <Typography.Paragraph type="secondary">
            {t('tunnel.confirmation.expire', { date: sejour.expire_le })}
          </Typography.Paragraph>
        )}
        {erreur && <Alert style={{ marginBottom: 12 }} type="error" showIcon title={erreur} />}
        {sejour.mode_reglement === 'en_ligne' && (
          <Button
            type="primary"
            size="large"
            loading={initiation === 'en_cours'}
            onClick={() => void payerMaintenant(sejour.reference)}
          >
            {t('tunnel.confirmation.payerMaintenant')}
          </Button>
        )}
      </div>
    </div>
  )
}

/**
 * Transformation d'un clic (CdC § 5.1) : le prix reste celui figé à l'établissement du devis,
 * quoi qu'il soit devenu depuis dans la grille — le serveur seul en décide, jamais cet écran.
 */
export function SectionTransformationDevis({
  reference,
  onTransforme,
}: {
  reference: string
  /** Prévient le parent (ex. la fiche du devis) qu'il doit cesser d'afficher les actions propres à un devis « en attente ». */
  onTransforme?: (sejour: Sejour) => void
}) {
  const { t } = useTranslation()
  const compteATerme = useQuery({ queryKey: ['compte-a-terme'], queryFn: monCompteATerme })
  const [modeReglement, setModeReglement] = useState<ModeReglement>('en_ligne')
  const [envoiEnCours, setEnvoiEnCours] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [sejour, setSejour] = useState<Sejour | null>(null)

  const transformer = async (): Promise<void> => {
    setErreur(null)
    setEnvoiEnCours(true)
    try {
      const resultat = await transformerLeDevis(reference, { mode_reglement: modeReglement })
      setSejour(resultat)
      onTransforme?.(resultat)
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    } finally {
      setEnvoiEnCours(false)
    }
  }

  if (sejour) {
    return <PageConfirmation resultat={{ type: 'sejour', sejour }} />
  }

  return (
    <div style={{ background: couleurs.sableClair, borderRadius: 8, padding: 20, marginTop: 24 }}>
      <Typography.Title level={4} style={{ marginTop: 0 }}>
        {t('tunnel.transformation.titre')}
      </Typography.Title>
      <Radio.Group value={modeReglement} onChange={(e) => setModeReglement(e.target.value as ModeReglement)}>
        <Space orientation="vertical">
          <Radio value="en_ligne">{t('tunnel.reglement.enLigne')}</Radio>
          <Radio value="agence">{t('tunnel.reglement.agence')}</Radio>
          {compteATerme.data?.statut === 'acceptee' && <Radio value="a_terme">{t('tunnel.reglement.aTerme')}</Radio>}
        </Space>
      </Radio.Group>

      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}

      <div style={{ marginTop: 16 }}>
        <Button type="primary" size="large" loading={envoiEnCours} onClick={() => void transformer()}>
          {t('tunnel.transformation.transformer')}
        </Button>
      </div>
    </div>
  )
}
