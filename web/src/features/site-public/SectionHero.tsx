import { LeftOutlined, RightOutlined, SearchOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Button, Carousel, Col, DatePicker, Row, Select, Typography, type CarouselRef } from 'antd'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { useRef, useState, type CSSProperties } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { lire } from '../../shared/api/client'
import { couleurs } from '../../shared/theme/jetons'
import { policeDisplay } from '../../shared/theme/jetonsSitePublic'
import type { CommuneChoix, Diapositive } from './types'

interface Props {
  /** Les diapositives configurées au back office (CdC § 12) : LE hero de l'accueil, pas une bannière secondaire plus bas. */
  diapositives: Diapositive[]
}

/**
 * En-tête de la page d'accueil (CdC § 5.1, § 12 ; brief refonte §14) : le slider EST le hero,
 * immédiatement sous l'en-tête — pas un carrousel promotionnel relégué plus bas dans la page.
 * La recherche reste fixe (toujours au même endroit, jamais un champ qui se déplace au fil des
 * diapositives) ; seuls le fond et le message au-dessus changent avec la diapositive active.
 * Sans diapositive configurée, le dégradé de secours (déjà en place avant cette refonte) sert de fond.
 */
export function SectionHero({ diapositives }: Props) {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [commune, setCommune] = useState<number | undefined>(undefined)
  const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(null)
  const [diapositiveActive, setDiapositiveActive] = useState(0)
  const carrousel = useRef<CarouselRef>(null)

  const communes = useQuery({ queryKey: ['referentiels', 'communes'], queryFn: () => lire<CommuneChoix[]>('/referentiels/communes') })

  const rechercher = (): void => {
    const params = new URLSearchParams()
    if (commune) params.set('commune_id', String(commune))
    if (dates) {
      params.set('arrivee', dates[0].format('YYYY-MM-DD'))
      params.set('depart', dates[1].format('YYYY-MM-DD'))
    }
    navigate(params.size > 0 ? `/recherche?${params.toString()}` : '/recherche')
  }

  const legende = diapositives[diapositiveActive]?.legende

  return (
    <div
      style={{
        position: 'relative',
        overflow: 'hidden',
        minHeight: 620,
        display: 'flex',
        flexDirection: 'column',
        justifyContent: 'flex-end',
        background: `linear-gradient(160deg, ${couleurs.bleuNuit} 0%, ${couleurs.bleuNuitFonce} 78%)`,
      }}
    >
      {diapositives.length > 0 && (
        <Carousel
          ref={carrousel}
          autoplay
          effect="fade"
          dots={false}
          afterChange={setDiapositiveActive}
          style={{ position: 'absolute', inset: 0 }}
        >
          {diapositives.map((d, i) => (
            <div key={d.id}>
              <img
                src={d.image}
                alt=""
                loading={i === 0 ? 'eager' : 'lazy'}
                fetchPriority={i === 0 ? 'high' : undefined}
                style={{ width: '100%', height: 620, objectFit: 'cover', display: 'block' }}
              />
            </div>
          ))}
        </Carousel>
      )}

      {/* Voile de lisibilité : toujours présent, sur photo comme sur dégradé de secours. */}
      <div
        aria-hidden
        style={{
          position: 'absolute',
          inset: 0,
          background: 'linear-gradient(180deg, rgba(21,43,71,0.35) 0%, rgba(21,43,71,0.55) 55%, rgba(21,43,71,0.88) 100%)',
        }}
      />

      {/* Un seul geste décoratif, discret : un arc de cercle en filigrane, jamais un motif répété. */}
      <div
        aria-hidden
        style={{
          position: 'absolute',
          top: '-38%',
          right: '-12%',
          width: 640,
          height: 640,
          borderRadius: '50%',
          border: `1px solid rgba(201, 154, 79, 0.22)`,
        }}
      />

      <div style={{ position: 'relative', maxWidth: 1120, margin: '0 auto', width: '100%', padding: '104px 24px 64px' }}>
        <Typography.Title
          level={1}
          style={{
            color: couleurs.blanc,
            marginBottom: 20,
            fontFamily: policeDisplay,
            fontWeight: 500,
            fontSize: 'clamp(34px, 5.4vw, 60px)',
            lineHeight: 1.08,
            maxWidth: 760,
          }}
        >
          {legende ?? t('accueil.titre')}
        </Typography.Title>
        <Typography.Paragraph style={{ fontSize: 18, color: 'rgba(255,255,255,0.78)', maxWidth: 560, marginBottom: 40 }}>
          {t('accueil.sousTitre')}
        </Typography.Paragraph>

        <div
          style={{
            background: couleurs.blanc,
            borderRadius: 10,
            padding: 22,
            boxShadow: '0 30px 70px rgba(9, 20, 36, 0.45)',
            maxWidth: 780,
          }}
        >
          <Row gutter={[16, 16]} align="middle">
            <Col xs={24} sm={8}>
              <Typography.Text strong style={{ display: 'block', marginBottom: 4, fontSize: 12, color: couleurs.texteDiscret }}>
                {t('recherche.filtre.commune')}
              </Typography.Text>
              <Select
                allowClear
                style={{ width: '100%' }}
                placeholder={t('recherche.filtre.toutesLesCommunes')}
                value={commune}
                onChange={setCommune}
                loading={communes.isPending}
                options={(communes.data ?? []).map((c) => ({ value: c.id, label: c.nom }))}
              />
            </Col>
            <Col xs={24} sm={10}>
              <Typography.Text strong style={{ display: 'block', marginBottom: 4, fontSize: 12, color: couleurs.texteDiscret }}>
                {t('recherche.filtre.arrivee')} — {t('recherche.filtre.depart')}
              </Typography.Text>
              <DatePicker.RangePicker
                style={{ width: '100%' }}
                value={dates}
                onChange={(v) => setDates(v && v[0] && v[1] ? [v[0], v[1]] : null)}
                disabledDate={(d) => d.isBefore(dayjs().startOf('day'))}
                placeholder={[t('recherche.filtre.arrivee'), t('recherche.filtre.depart')]}
              />
            </Col>
            <Col xs={24} sm={6}>
              <Button type="primary" icon={<SearchOutlined />} block size="large" onClick={rechercher}>
                {t('recherche.filtre.rechercher')}
              </Button>
            </Col>
          </Row>
        </div>

        {diapositives.length > 1 && (
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 28 }}>
            <button type="button" className="carrousel-fleche" aria-label="Diapositive précédente" onClick={() => carrousel.current?.prev()} style={styleFlecheInline}>
              <LeftOutlined />
            </button>
            <div style={{ display: 'flex', gap: 6 }}>
              {diapositives.map((d, i) => (
                <span
                  key={d.id}
                  style={{
                    width: i === diapositiveActive ? 22 : 7,
                    height: 7,
                    borderRadius: 999,
                    background: i === diapositiveActive ? couleurs.blanc : 'rgba(255,255,255,0.45)',
                    transition: 'width 0.2s ease, background 0.2s ease',
                  }}
                />
              ))}
            </div>
            <button type="button" className="carrousel-fleche" aria-label="Diapositive suivante" onClick={() => carrousel.current?.next()} style={styleFlecheInline}>
              <RightOutlined />
            </button>
          </div>
        )}
      </div>
    </div>
  )
}

const styleFlecheInline: CSSProperties = {
  width: 36,
  height: 36,
  borderRadius: '50%',
  border: '1px solid rgba(255,255,255,0.4)',
  background: 'transparent',
  color: couleurs.blanc,
  display: 'flex',
  alignItems: 'center',
  justifyContent: 'center',
  cursor: 'pointer',
  transition: 'background 0.15s ease',
}
