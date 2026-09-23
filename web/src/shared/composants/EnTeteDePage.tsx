import { Space, Typography } from 'antd'
import type { ReactNode } from 'react'
import { couleurs } from '../theme/jetons'

interface Props {
  titre: string
  description?: string
  actions?: ReactNode
}

/**
 * Factorise le triptyque titre + description + actions répété manuellement dans chaque écran
 * de liste (Réservations, Catalogue, Propriétaires, Caisse…) — même structure, sans changer
 * la disposition déjà en place (flex space-between, marginBottom 16).
 */
export function EnTeteDePage({ titre, description, actions }: Props) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16, marginBottom: 16, flexWrap: 'wrap' }}>
      <div>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {titre}
        </Typography.Title>
        {description && (
          <Typography.Text style={{ color: couleurs.texteDiscret, fontSize: 14 }}>{description}</Typography.Text>
        )}
      </div>
      {actions && <Space wrap>{actions}</Space>}
    </div>
  )
}
