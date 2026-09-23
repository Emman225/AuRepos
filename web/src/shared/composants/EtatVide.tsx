import { InboxOutlined } from '@ant-design/icons'
import { Button, Typography } from 'antd'
import type { ReactNode } from 'react'
import { couleurs } from '../theme/jetons'

interface Props {
  titre: string
  description?: string
  action?: { libelle: string; onClick: () => void }
  icone?: ReactNode
}

/** Absence de données expliquée (pourquoi + quoi faire), jamais un simple tableau vide (brief §27). */
export function EtatVide({ titre, description, action, icone }: Props) {
  return (
    <div style={{ textAlign: 'center', padding: '48px 24px' }}>
      <div style={{ fontSize: 40, color: couleurs.sable, marginBottom: 12 }}>{icone ?? <InboxOutlined />}</div>
      <Typography.Text strong style={{ display: 'block', fontSize: 15, color: couleurs.texte }}>
        {titre}
      </Typography.Text>
      {description && (
        <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, fontSize: 13, marginTop: 4, maxWidth: 380, marginInline: 'auto' }}>
          {description}
        </Typography.Text>
      )}
      {action && (
        <Button type="primary" onClick={action.onClick} style={{ marginTop: 16 }}>
          {action.libelle}
        </Button>
      )}
    </div>
  )
}
