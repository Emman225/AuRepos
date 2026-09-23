import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { KPI } from '../../shared/composants/KPI'
import { formaterPrix } from '../../shared/format/devise'
import { mesGains } from './api'

/** Espace chauffeur › Mes gains (CdC § 6.6) : total_gagne / solde_du en évidence, même patron que le tableau de bord apporteur. */
export function PageGains() {
  const { t } = useTranslation()
  const gains = useQuery({ queryKey: ['chauffeur', 'gains'], queryFn: mesGains })

  return (
    <div>
      <EnTeteDePage titre={t('chauffeur.menu.gains')} />

      {gains.isPending ? (
        <Skeleton active paragraph={{ rows: 3 }} />
      ) : !gains.data ? null : (
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={12}>
            <KPI taille="grand" libelle={t('chauffeur.gains.totalGagne')} valeur={formaterPrix(gains.data.total_gagne)} />
          </Col>
          <Col xs={24} sm={12}>
            <KPI
              taille="grand"
              libelle={t('chauffeur.gains.soldeDu')}
              valeur={formaterPrix(gains.data.solde_du)}
              tonalite={gains.data.solde_du > 0 ? 'succes' : 'neutre'}
            />
          </Col>
        </Row>
      )}
    </div>
  )
}
