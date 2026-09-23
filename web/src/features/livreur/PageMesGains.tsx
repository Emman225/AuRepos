import { useQuery } from '@tanstack/react-query'
import { Skeleton, Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { mesGains } from './api'

/** Espace livreur › ses gains (CdC — « consulte ses gains »). */
export function PageMesGains() {
  const { t } = useTranslation()
  const gains = useQuery({ queryKey: ['livreur', 'gains'], queryFn: mesGains })

  return (
    <div>
      <EnTeteDePage titre={t('livreur.menu.gains')} />

      {gains.isPending ? (
        <Skeleton active paragraph={{ rows: 3 }} />
      ) : (
        gains.data && (
          <Space orientation="vertical" size={16}>
            <div>
              <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                {t('livreur.tableauDeBord.soldeDu')}
              </Typography.Text>
              <Typography.Text strong style={{ fontSize: 26, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
                {formaterPrix(gains.data.solde_du)}
              </Typography.Text>
            </div>
            <div>
              <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                {t('livreur.gains.totalGagne')}
              </Typography.Text>
              <Typography.Text strong style={{ fontSize: 18, fontVariantNumeric: 'tabular-nums' }}>
                {formaterPrix(gains.data.total_gagne)}
              </Typography.Text>
            </div>
            <div>
              <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                {t('livreur.gains.dejaVerse')}
              </Typography.Text>
              <Typography.Text strong style={{ fontSize: 18, fontVariantNumeric: 'tabular-nums' }}>
                {formaterPrix(gains.data.deja_verse)}
              </Typography.Text>
            </div>
          </Space>
        )
      )}
    </div>
  )
}
