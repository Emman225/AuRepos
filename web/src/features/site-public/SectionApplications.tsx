import { CheckOutlined } from '@ant-design/icons'
import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { couleurs } from '../../shared/theme/jetons'
import { laitonClair, policeDisplay } from '../../shared/theme/jetonsSitePublic'

/** Silhouette de téléphone dessinée en CSS : pas de photo de maquette à ce stade du projet. */
function MaquetteTelephone() {
  return (
    <div
      style={{
        width: 168,
        height: 336,
        borderRadius: 26,
        border: `2px solid ${couleurs.bleuNuitFonce}`,
        background: 'linear-gradient(165deg, #24456E 0%, #152B47 100%)',
        boxShadow: '0 30px 60px -25px rgba(0,0,0,0.55)',
        padding: 10,
        flexShrink: 0,
      }}
    >
      <div style={{ height: '100%', borderRadius: 16, background: 'rgba(255,255,255,0.06)', padding: 16, display: 'flex', flexDirection: 'column', gap: 10 }}>
        <div style={{ width: 44, height: 4, borderRadius: 2, background: 'rgba(255,255,255,0.25)', margin: '0 auto 6px' }} />
        <div style={{ width: '70%', height: 8, borderRadius: 4, background: 'rgba(255,255,255,0.3)' }} />
        <div style={{ width: '45%', height: 8, borderRadius: 4, background: 'rgba(255,255,255,0.18)', marginBottom: 8 }} />
        <div style={{ flex: 1, borderRadius: 10, background: 'rgba(255,255,255,0.08)' }} />
        <div style={{ height: 34, borderRadius: 8, background: laitonClair }} />
      </div>
    </div>
  )
}

/**
 * « Nos applications mobiles » (CdC § 5.1, § 13.3) : distribuées depuis cette page avant
 * d'atteindre les magasins. Aucune application Flutter n'est encore publiée (lots 2 à 4) —
 * traité comme une annonce éditoriale sobre plutôt qu'un rectangle d'espace réservé.
 */
export function SectionApplications() {
  const { t } = useTranslation()
  const arguments_ = [t('accueil.applications.argument1'), t('accueil.applications.argument2'), t('accueil.applications.argument3')]

  return (
    <section
      style={{
        background: couleurs.bleuNuit,
        borderRadius: 20,
        padding: '48px 40px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 40,
        flexWrap: 'wrap',
        overflow: 'hidden',
      }}
    >
      <div style={{ maxWidth: 420 }}>
        <Typography.Title level={2} style={{ color: couleurs.blanc, fontFamily: policeDisplay, marginTop: 0 }}>
          {t('accueil.applications.titre')}
        </Typography.Title>
        <Typography.Paragraph style={{ color: 'rgba(255,255,255,0.75)', fontSize: 15, marginBottom: 24 }}>
          {t('accueil.applications.bientot')}
        </Typography.Paragraph>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          {arguments_.map((argument) => (
            <div key={argument} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
              <span
                style={{
                  width: 20,
                  height: 20,
                  borderRadius: '50%',
                  background: 'rgba(255,255,255,0.12)',
                  color: laitonClair,
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  fontSize: 11,
                  flexShrink: 0,
                }}
              >
                <CheckOutlined />
              </span>
              <Typography.Text style={{ color: 'rgba(255,255,255,0.9)', fontSize: 14 }}>{argument}</Typography.Text>
            </div>
          ))}
        </div>
      </div>
      <MaquetteTelephone />
    </section>
  )
}
