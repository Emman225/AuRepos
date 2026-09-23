import { Tabs, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { useSession } from '../../auth/session'
import { OngletAvances } from './OngletAvances'
import { OngletDecaissement } from './OngletDecaissement'
import { OngletEncaissement } from './OngletEncaissement'
import { OngletFileDeValidation } from './OngletFileDeValidation'
import { OngletRegistre } from './OngletRegistre'

/** Back office › Caisse : guichets Séjours et Créances à terme, file de validation, avances (CdC § 8.1). */
export function PageCaisse() {
  const { t } = useTranslation()
  const estAdministrateur = useSession((s) => s.utilisateur?.profil === 'administrateur' || s.utilisateur?.profil === 'super_administrateur')

  const items = [
    { key: 'encaissement', label: t('backoffice.caisse.onglets.encaissement'), children: <OngletEncaissement /> },
    { key: 'fileDeValidation', label: t('backoffice.caisse.onglets.fileDeValidation'), children: <OngletFileDeValidation /> },
    { key: 'avances', label: t('backoffice.caisse.onglets.avances'), children: <OngletAvances /> },
    { key: 'registre', label: t('backoffice.caisse.onglets.registre'), children: <OngletRegistre /> },
  ]
  if (estAdministrateur) {
    items.push({ key: 'decaissement', label: t('backoffice.caisse.onglets.decaissement'), children: <OngletDecaissement /> })
  }

  return (
    <div>
      <Typography.Title level={3}>{t('backoffice.menu.caisse')}</Typography.Title>
      <Tabs items={items} />
    </div>
  )
}
