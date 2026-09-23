import { CalendarOutlined, EnvironmentOutlined, HomeOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { formaterDate } from '../../shared/format/date'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import type { Sejour } from '../reservation/types'
import { mesSejours } from './api'

/** Une carte séjour plutôt qu'une ligne de tableau : le client lit son séjour, il ne l'exploite pas (brief refonte §4). */
function CarteSejour({ sejour }: { sejour: Sejour }) {
  const { t } = useTranslation()
  return (
    <Link
      to={sejour.reference}
      className="kpi-actionnable"
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 16,
        padding: '16px 20px',
        background: couleurs.blanc,
        border: `1px solid ${couleurs.bordure}`,
        borderRadius: 10,
        marginBottom: 12,
      }}
    >
      <div
        style={{
          width: 48,
          height: 48,
          borderRadius: 8,
          background: couleurs.sableClair,
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          flexShrink: 0,
        }}
      >
        <HomeOutlined style={{ fontSize: 20, color: couleurs.sable }} />
      </div>

      <div style={{ flex: 1, minWidth: 0 }}>
        <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>{sejour.reference}</Typography.Text>
        <Typography.Text strong style={{ display: 'block', fontSize: 15 }}>
          {sejour.logement.nom}
        </Typography.Text>
        <Typography.Text style={{ display: 'flex', alignItems: 'center', gap: 6, color: couleurs.texteDiscret, fontSize: 13, marginTop: 2 }}>
          <EnvironmentOutlined /> {sejour.logement.lieu.quartier}, {sejour.logement.lieu.commune}
        </Typography.Text>
        <Typography.Text style={{ display: 'flex', alignItems: 'center', gap: 6, color: couleurs.texteDiscret, fontSize: 13, marginTop: 2 }}>
          <CalendarOutlined /> {formaterDate(sejour.arrivee)} → {formaterDate(sejour.depart)}
        </Typography.Text>
      </div>

      <div style={{ textAlign: 'right', flexShrink: 0 }}>
        <StatutBadge domaine="sejour" code={sejour.etat} libelle={sejour.etat_libelle} />
        <Typography.Text strong style={{ display: 'block', marginTop: 8, fontSize: 15, fontVariantNumeric: 'tabular-nums' }}>
          {formaterPrix(sejour.net_a_payer)}
        </Typography.Text>
        <Typography.Text style={{ fontSize: 12, color: couleurs.texteDiscret }}>{t('client.sejours.netAPayer')}</Typography.Text>
      </div>
    </Link>
  )
}

/** Espace client › Mes séjours (CdC § 5.3). */
export function PageMesSejours() {
  const { t } = useTranslation()
  const sejours = useQuery({ queryKey: ['client', 'sejours'], queryFn: mesSejours })

  return (
    <div>
      <Typography.Title level={3} style={{ marginBottom: 20 }}>
        {t('client.menu.sejours')}
      </Typography.Title>

      {sejours.isPending ? (
        <Skeleton active paragraph={{ rows: 6 }} />
      ) : !sejours.data || sejours.data.elements.length === 0 ? (
        <Typography.Text type="secondary">{t('client.sejours.aucun')}</Typography.Text>
      ) : (
        sejours.data.elements.map((s) => <CarteSejour key={s.reference} sejour={s} />)
      )}
    </div>
  )
}
