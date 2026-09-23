import {
  ApartmentOutlined,
  CarOutlined,
  CheckCircleOutlined,
  CheckOutlined,
  CloseOutlined,
  CloudOutlined,
  CoffeeOutlined,
  EnvironmentOutlined,
  FireOutlined,
  InboxOutlined,
  MonitorOutlined,
  RestOutlined,
  SafetyCertificateOutlined,
  StarFilled,
  SyncOutlined,
  ThunderboltOutlined,
  TrophyOutlined,
  VerticalAlignMiddleOutlined,
  WifiOutlined,
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Col, Empty, Rate, Row, Skeleton, Space, Typography } from 'antd'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi, lire } from '../../shared/api/client'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { laiton, policeDisplay } from '../../shared/theme/jetonsSitePublic'
import { BaliseSeo } from './BaliseSeo'
import { CalendrierDisponibilite } from './CalendrierDisponibilite'
import { CarteLogement } from './CarteLogement'
import { GalerieLogement } from './GalerieLogement'
import type { LogementFiche } from './types'

/** Icônes des équipements (api/database/seeders/ReferentielsSeeder.php) — même famille "outlined", même épaisseur (brief §36). */
const ICONE_EQUIPEMENT: Record<string, ReactNode> = {
  wifi: <WifiOutlined />,
  snowflake: <CloudOutlined />,
  pool: <RestOutlined />,
  car: <CarOutlined />,
  bolt: <ThunderboltOutlined />,
  shield: <SafetyCertificateOutlined />,
  tv: <MonitorOutlined />,
  kitchen: <CoffeeOutlined />,
  fridge: <InboxOutlined />,
  washer: <SyncOutlined />,
  shower: <FireOutlined />,
  balcony: <ApartmentOutlined />,
  elevator: <VerticalAlignMiddleOutlined />,
  dumbbell: <TrophyOutlined />,
}

function Regle({ label, autorise }: { label: string; autorise: boolean }) {
  return (
    <Space>
      {autorise ? (
        <CheckOutlined style={{ color: couleurs.succes }} />
      ) : (
        <CloseOutlined style={{ color: couleurs.erreur }} />
      )}
      <Typography.Text>{label}</Typography.Text>
    </Space>
  )
}

/** Un chiffre essentiel (capacité, chambres, surface…) — la donnée existe déjà côté API mais n'était affichée nulle part. */
function StatEssentielle({ valeur, libelle }: { valeur: string; libelle: string }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column' }}>
      <Typography.Text strong style={{ fontSize: 16, color: couleurs.texte, lineHeight: 1.2 }}>
        {valeur}
      </Typography.Text>
      <Typography.Text style={{ fontSize: 12, color: couleurs.texteDiscret }}>{libelle}</Typography.Text>
    </div>
  )
}

/** Fiche logement (CdC § 5.1). */
export function PageFicheLogement() {
  const { t } = useTranslation()
  const { reference } = useParams<{ reference: string }>()

  const fiche = useQuery({
    queryKey: ['catalogue', 'logement', reference],
    queryFn: () => lire<LogementFiche>(`/catalogue/logements/${reference}`),
    enabled: !!reference,
  })

  if (fiche.isPending) {
    return (
      <div style={{ padding: '48px 24px', maxWidth: 1100, margin: '0 auto' }}>
        <Skeleton active paragraph={{ rows: 8 }} />
      </div>
    )
  }

  if (fiche.isError) {
    return (
      <div style={{ padding: '48px 24px', maxWidth: 1100, margin: '0 auto' }}>
        <Alert
          type="error"
          showIcon
          title={
            fiche.error instanceof ErreurApi && fiche.error.statut === 404 ? t('fiche.introuvable') : t('fiche.erreur')
          }
        />
      </div>
    )
  }

  const logement = fiche.data
  const lienGoogleMaps = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${logement.lieu.quartier}, ${logement.lieu.commune}, Abidjan`)}`

  return (
    <div style={{ padding: '48px 24px', maxWidth: 1100, margin: '0 auto', width: '100%' }}>
      <BaliseSeo
        titre={`${logement.nom} — ${logement.lieu.quartier}, ${logement.lieu.commune}`}
        description={logement.resume}
        chemin={`/logements/${logement.reference}`}
        image={logement.photos[0]?.url}
        donneesStructurees={{
          '@context': 'https://schema.org',
          '@type': 'LodgingBusiness',
          name: logement.nom,
          description: logement.resume,
          image: logement.photos.map((p) => p.url),
          address: { '@type': 'PostalAddress', addressLocality: logement.lieu.quartier, addressRegion: logement.lieu.commune, addressCountry: 'CI' },
          priceRange: formaterPrix(logement.prix_par_nuit),
        }}
      />
      <Space orientation="vertical" size="large" style={{ width: '100%' }}>
        <div>
          <Space align="center" size={10} style={{ marginBottom: 6 }}>
            <EnvironmentOutlined style={{ color: couleurs.texteDiscret, fontSize: 13 }} />
            <Typography.Text style={{ color: couleurs.texteDiscret, fontSize: 13 }}>
              {logement.lieu.quartier}, {logement.lieu.commune}
            </Typography.Text>
          </Space>
          <div style={{ display: 'flex', alignItems: 'center', gap: 14, flexWrap: 'wrap' }}>
            <Typography.Title
              level={1}
              style={{ margin: 0, fontFamily: policeDisplay, fontWeight: 500, fontSize: 'clamp(28px, 4vw, 42px)' }}
            >
              {logement.nom}
            </Typography.Title>
            {logement.note_moyenne !== null && (
              <Space size={4} style={{ color: couleurs.texte, fontSize: 15 }}>
                <StarFilled style={{ color: laiton, fontSize: 15 }} />
                <Typography.Text strong>{logement.note_moyenne}</Typography.Text>
              </Space>
            )}
          </div>
          <Typography.Text style={{ color: couleurs.texteDiscret, display: 'block', marginTop: 6 }}>
            {logement.resume}
          </Typography.Text>
        </div>

        <GalerieLogement photos={logement.photos} nom={logement.nom} />

        <div
          style={{
            display: 'flex',
            flexWrap: 'wrap',
            gap: 28,
            padding: '18px 22px',
            border: `1px solid ${couleurs.bordure}`,
            borderRadius: 10,
          }}
        >
          <StatEssentielle valeur={logement.type.nom} libelle={t('recherche.filtre.type')} />
          <StatEssentielle
            valeur={String(logement.capacite_maximale)}
            libelle={t('fiche.info.capacite')}
          />
          <StatEssentielle
            valeur={String(logement.nombre_chambres)}
            libelle={t('fiche.info.chambres', { count: logement.nombre_chambres })}
          />
          {logement.nombre_lits !== null && (
            <StatEssentielle valeur={String(logement.nombre_lits)} libelle={t('fiche.info.lits', { count: logement.nombre_lits })} />
          )}
          {logement.nombre_salles_de_bain !== null && (
            <StatEssentielle
              valeur={String(logement.nombre_salles_de_bain)}
              libelle={t('fiche.info.sdb', { count: logement.nombre_salles_de_bain })}
            />
          )}
          {logement.surface_m2 !== null && (
            <StatEssentielle valeur={`${logement.surface_m2} ${t('fiche.info.surface')}`} libelle={logement.residence.nom} />
          )}
        </div>

        <Row gutter={32}>
          <Col xs={24} md={16}>
            <Space orientation="vertical" size="large" style={{ width: '100%' }}>
              {logement.description && <Typography.Paragraph>{logement.description}</Typography.Paragraph>}

              <section>
                <Typography.Title level={3}>{t('fiche.equipements.titre')}</Typography.Title>
                {logement.equipements.length === 0 ? (
                  <Typography.Text type="secondary">{t('fiche.equipements.aucun')}</Typography.Text>
                ) : (
                  <Row gutter={[16, 14]}>
                    {logement.equipements.map((e) => (
                      <Col key={e.nom} xs={12} sm={8}>
                        <Space size={10}>
                          <span style={{ color: laiton, fontSize: 17 }}>
                            {(e.icone && ICONE_EQUIPEMENT[e.icone]) ?? <CheckCircleOutlined />}
                          </span>
                          <Typography.Text>{e.nom}</Typography.Text>
                        </Space>
                      </Col>
                    ))}
                  </Row>
                )}
              </section>

              <section>
                <Typography.Title level={3}>{t('fiche.regles.titre')}</Typography.Title>
                <Space orientation="vertical">
                  <Regle label={t('fiche.regles.fumeur')} autorise={logement.regles.fumeur_autorise} />
                  <Regle label={t('fiche.regles.animaux')} autorise={logement.regles.animaux_autorises} />
                  <Regle label={t('fiche.regles.fetes')} autorise={logement.regles.fetes_autorisees} />
                  <Typography.Text type="secondary">
                    {t('fiche.regles.horaires', { arrivee: logement.heure_arrivee, depart: logement.heure_depart })}
                  </Typography.Text>
                  {logement.regles.texte && <Typography.Paragraph>{logement.regles.texte}</Typography.Paragraph>}
                </Space>
              </section>

              <section>
                <Typography.Title level={3}>{t('fiche.lieu.titre')}</Typography.Title>
                <Typography.Paragraph>{t('fiche.lieu.avertissement')}</Typography.Paragraph>
                <a href={lienGoogleMaps} target="_blank" rel="noreferrer">
                  <EnvironmentOutlined /> {logement.lieu.quartier}, {logement.lieu.commune}
                </a>
              </section>

              <section>
                <Typography.Title level={3}>{t('fiche.calendrier.titre')}</Typography.Title>
                {reference && <CalendrierDisponibilite reference={reference} />}
              </section>

              {logement.tarifs_par_saison.length > 0 && (
                <section>
                  <Typography.Title level={3}>{t('fiche.tarifs.titre')}</Typography.Title>
                  <Space orientation="vertical" style={{ width: '100%' }}>
                    {logement.tarifs_par_saison.map((t2) => (
                      <Row
                        key={t2.saison}
                        justify="space-between"
                        style={{ borderBottom: `1px solid ${couleurs.bordure}`, paddingBottom: 4 }}
                      >
                        <Typography.Text>{t2.saison}</Typography.Text>
                        <Typography.Text strong>
                          {t('recherche.prixParNuit', { prix: formaterPrix(t2.tarif) })}
                        </Typography.Text>
                      </Row>
                    ))}
                  </Space>
                </section>
              )}

              <section>
                <Typography.Title level={3} style={{ marginBottom: 12 }}>
                  {t('fiche.avis.titre')}
                </Typography.Title>
                {logement.avis.length === 0 ? (
                  <Empty description={t('fiche.avis.aucun')} />
                ) : (
                  <Space orientation="vertical" size="middle" style={{ width: '100%' }}>
                    {logement.avis.map((a, i) => (
                      <div key={i} style={{ borderBottom: `1px solid ${couleurs.bordure}`, paddingBottom: 12 }}>
                        <Space align="center" style={{ marginBottom: 4 }}>
                          <Typography.Text strong>{a.client}</Typography.Text>
                          <Rate disabled value={a.note} style={{ fontSize: 14 }} />
                          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                            {a.depose_le}
                          </Typography.Text>
                        </Space>
                        {a.commentaire && <Typography.Paragraph style={{ margin: 0 }}>{a.commentaire}</Typography.Paragraph>}
                      </div>
                    ))}
                  </Space>
                )}
              </section>
            </Space>
          </Col>

          <Col xs={24} md={8}>
            <div style={{ background: couleurs.blanc, border: `1px solid ${couleurs.bordure}`, borderRadius: 10, padding: 22, position: 'sticky', top: 20 }}>
              <Typography.Text
                style={{ display: 'block', fontFamily: policeDisplay, fontWeight: 500, fontSize: 30, color: couleurs.texte }}
              >
                {formaterPrix(logement.prix_par_nuit)}
              </Typography.Text>
              <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, fontSize: 13, marginBottom: 18 }}>
                {t('recherche.parNuit')}
              </Typography.Text>

              <Link to={`/logements/${logement.reference}/reserver`}>
                <Button type="primary" size="large" block>
                  {t('fiche.reserver')}
                </Button>
              </Link>

              <div style={{ marginTop: 18, paddingTop: 16, borderTop: `1px solid ${couleurs.bordure}` }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
                  <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret }}>{t('fiche.cautionLibelle')}</Typography.Text>
                  <Typography.Text strong style={{ fontSize: 13 }}>
                    {formaterPrix(logement.caution)}
                  </Typography.Text>
                </div>
                <Typography.Text style={{ fontSize: 12, color: couleurs.texteDiscret, display: 'block' }}>
                  {logement.politique_annulation_libelle}
                </Typography.Text>
                {logement.duree_minimale && (
                  <Typography.Text style={{ fontSize: 12, color: couleurs.texteDiscret, display: 'block', marginTop: 4 }}>
                    {t('fiche.dureeMinimale', { count: logement.duree_minimale })}
                  </Typography.Text>
                )}
              </div>
            </div>
          </Col>
        </Row>

        {logement.similaires.length > 0 && (
          <section>
            <Typography.Title level={2}>{t('fiche.similaires.titre')}</Typography.Title>
            <Row gutter={[16, 16]}>
              {logement.similaires.map((l) => (
                <Col key={l.reference} xs={24} sm={12} lg={6}>
                  <CarteLogement logement={l} />
                </Col>
              ))}
            </Row>
          </section>
        )}
      </Space>
    </div>
  )
}
