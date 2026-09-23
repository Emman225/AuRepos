import { Tabs } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { OngletAgences } from './OngletAgences'
import { OngletPersonnel } from './OngletPersonnel'

/** Back office › Personnel et agences (CdC § 9.5). */
export function PageEquipe() {
  const { t } = useTranslation()

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.personnel')} />
      <Tabs
        items={[
          { key: 'personnel', label: t('backoffice.personnel.onglets.personnel'), children: <OngletPersonnel /> },
          { key: 'agences', label: t('backoffice.personnel.onglets.agences'), children: <OngletAgences /> },
        ]}
      />
    </div>
  )
}
