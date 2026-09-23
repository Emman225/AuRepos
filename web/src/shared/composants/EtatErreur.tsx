import { ExclamationCircleOutlined } from '@ant-design/icons'
import { Button, Typography } from 'antd'
import { tonsSemantiques } from '../theme/jetons'

interface Props {
  titre: string
  description?: string
  reessayer?: () => void
}

/** Message humain + action possible, jamais une erreur technique brute affichée telle quelle. */
export function EtatErreur({ titre, description, reessayer }: Props) {
  const tons = tonsSemantiques.erreur

  return (
    <div style={{ textAlign: 'center', padding: '48px 24px', background: tons.fond, borderRadius: 10, border: `1px solid ${tons.bordure}` }}>
      <ExclamationCircleOutlined style={{ fontSize: 32, color: tons.texte, marginBottom: 10 }} />
      <Typography.Text strong style={{ display: 'block', fontSize: 15, color: tons.texte }}>
        {titre}
      </Typography.Text>
      {description && (
        <Typography.Text style={{ display: 'block', color: tons.texte, opacity: 0.85, fontSize: 13, marginTop: 4 }}>
          {description}
        </Typography.Text>
      )}
      {reessayer && (
        <Button onClick={reessayer} style={{ marginTop: 16 }}>
          Réessayer
        </Button>
      )}
    </div>
  )
}
