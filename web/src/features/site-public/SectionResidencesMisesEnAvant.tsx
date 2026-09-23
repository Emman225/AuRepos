import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { CarteResidence } from './CarteResidence'
import type { VignetteResidence } from './types'

interface Props {
  residences: VignetteResidence[]
}

/** Résidences meublées mises en avant (CdC § 5.1), au-dessus des catégories : un film à parcourir, pas une grille. */
export function SectionResidencesMisesEnAvant({ residences }: Props) {
  const { t } = useTranslation()
  if (residences.length === 0) return null

  return (
    <section>
      <Typography.Title level={2}>{t('accueil.residencesMisesEnAvant.titre')}</Typography.Title>
      <div
        style={{
          display: 'flex',
          gap: 20,
          overflowX: 'auto',
          paddingBottom: 8,
          scrollSnapType: 'x proximity',
        }}
      >
        {residences.map((residence) => (
          <div key={residence.slug} style={{ flex: '0 0 300px', scrollSnapAlign: 'start' }}>
            <CarteResidence residence={residence} />
          </div>
        ))}
      </div>
    </section>
  )
}
