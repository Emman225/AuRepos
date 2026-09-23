import { Tabs } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { OngletClients } from './OngletClients'
import { OngletComptesATerme } from './OngletComptesATerme'

/** Back office › Clients : ordinaires, à terme, TVA, liste noire (CdC § 5). */
export function PageClients() {
  const { t } = useTranslation()

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.clients')} />
      <Tabs
        items={[
          { key: 'clients', label: t('backoffice.clients.onglets.clients'), children: <OngletClients /> },
          { key: 'aTerme', label: t('backoffice.clients.onglets.aTerme'), children: <OngletComptesATerme /> },
        ]}
      />
    </div>
  )
}
