import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { LigneResidenceClassee } from './LigneResidenceClassee'
import type { VignetteResidence } from './types'

interface Props {
  residences: VignetteResidence[]
}

/** Résidences meublées les mieux notées (P2-AVI-01), sous les catégories de logement : un vrai classement. */
export function SectionMeilleuresResidences({ residences }: Props) {
  const { t } = useTranslation()
  if (residences.length === 0) return null

  return (
    <section>
      <Typography.Title level={2}>{t('accueil.meilleuresResidences.titre')}</Typography.Title>
      <div style={{ maxWidth: 720 }}>
        {residences.map((residence, i) => (
          <LigneResidenceClassee key={residence.slug} rang={i + 1} residence={residence} />
        ))}
      </div>
    </section>
  )
}
