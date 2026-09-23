import { Carousel, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import type { Banniere } from './types'

interface Props {
  bannieres: Banniere[]
}

/** Bannières promotionnelles (CdC § 5.1 « promotions »), saisies au back office (P1-BO-10). */
export function SectionPromotions({ bannieres }: Props) {
  const { t } = useTranslation()
  if (bannieres.length === 0) return null

  return (
    <section>
      <Typography.Title level={2}>{t('accueil.promotions.titre')}</Typography.Title>
      <Carousel autoplay>
        {bannieres.map((banniere) => {
          const image = (
            <img
              src={banniere.image}
              alt={banniere.titre}
              loading="lazy"
              style={{ width: '100%', maxHeight: 320, objectFit: 'cover' }}
            />
          )
          return <div key={banniere.id}>{banniere.lien ? <a href={banniere.lien}>{image}</a> : image}</div>
        })}
      </Carousel>
    </section>
  )
}
