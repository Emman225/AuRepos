import { Col, Row, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { CarteLogement } from './CarteLogement'
import type { VignetteLogement } from './types'

interface Props {
  logements: VignetteLogement[]
}

/** Résidences et logements mis en avant (CdC § 5.1). */
export function SectionMisesEnAvant({ logements }: Props) {
  const { t } = useTranslation()
  if (logements.length === 0) return null

  return (
    <section>
      <Typography.Title level={2}>{t('accueil.misesEnAvant.titre')}</Typography.Title>
      <Row gutter={[16, 16]}>
        {logements.map((logement) => (
          <Col key={logement.reference} xs={24} sm={12} lg={8} xl={6}>
            <CarteLogement logement={logement} />
          </Col>
        ))}
      </Row>
    </section>
  )
}
