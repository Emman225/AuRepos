import { Tabs } from 'antd'
import { useTranslation } from 'react-i18next'
import { OngletBannieres } from './OngletBannieres'
import { OngletBlog } from './OngletBlog'
import { OngletCarrousel } from './OngletCarrousel'
import { OngletNewsletter } from './OngletNewsletter'

/** Paramètres › Divers (CdC § 12, P1-BO-10) : blog, bannières, carrousel, lettre d'information. */
export function OngletDivers() {
  const { t } = useTranslation()

  return (
    <Tabs
      items={[
        { key: 'blog', label: t('backoffice.parametres.diversOnglets.blog'), children: <OngletBlog /> },
        { key: 'bannieres', label: t('backoffice.parametres.diversOnglets.bannieres'), children: <OngletBannieres /> },
        { key: 'carrousel', label: t('backoffice.parametres.diversOnglets.carrousel'), children: <OngletCarrousel /> },
        { key: 'newsletter', label: t('backoffice.parametres.diversOnglets.newsletter'), children: <OngletNewsletter /> },
      ]}
    />
  )
}
