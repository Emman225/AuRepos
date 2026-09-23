import { useQuery } from '@tanstack/react-query'
import { Alert, Space, Spin, Tabs } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { listerLesParametres } from './api'
import { OngletDivers } from './OngletDivers'
import { OngletGenerique } from './OngletGenerique'
import type { Onglet } from './types'

/** Back office › Paramètres (CdC § 12) : les onglets du lot 1, puis « Divers » (P1-BO-10). */
export function PageParametres() {
  const { t } = useTranslation()
  const parametres = useQuery({ queryKey: ['backoffice', 'parametres'], queryFn: listerLesParametres })
  const [ongletsAJour, setOngletsAJour] = useState<Onglet[] | null>(null)

  const onglets = ongletsAJour ?? parametres.data?.onglets ?? []

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.parametres')} />

      {parametres.isPending ? (
        <Spin />
      ) : (
        <Space orientation="vertical" style={{ width: '100%' }}>
          {(parametres.data?.alertes ?? []).map((a) => (
            <Alert key={a} type="error" showIcon title={a} />
          ))}
          <Tabs
            items={[
              ...onglets.map((onglet) => ({
                key: onglet.code,
                label: onglet.libelle,
                children: (
                  <OngletGenerique onglet={onglet} administrateurs={parametres.data?.administrateurs ?? []} onEnregistre={setOngletsAJour} />
                ),
              })),
              { key: 'divers', label: t('backoffice.parametres.divers'), children: <OngletDivers /> },
            ]}
          />
        </Space>
      )}
    </div>
  )
}
