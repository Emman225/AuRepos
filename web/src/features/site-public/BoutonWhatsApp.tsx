import { WhatsAppOutlined } from '@ant-design/icons'
import { FloatButton } from 'antd'

interface Props {
  numero: string | null
}

/** Bouton WhatsApp flottant (CdC § 5.1), numéro saisi dans les Paramètres. */
export function BoutonWhatsApp({ numero }: Props) {
  if (!numero) return null
  const chiffres = numero.replace(/\D/g, '')
  if (chiffres === '') return null

  return (
    <FloatButton
      icon={<WhatsAppOutlined />}
      href={`https://wa.me/${chiffres}`}
      target="_blank"
      tooltip="WhatsApp"
      aria-label="WhatsApp"
      style={{ insetInlineEnd: 24, insetBlockEnd: 24, background: '#25D366', color: '#fff' }}
    />
  )
}
