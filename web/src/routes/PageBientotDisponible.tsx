import { ClockCircleOutlined } from '@ant-design/icons'
import { Result } from 'antd'
import { useTranslation } from 'react-i18next'

interface Props {
  titre: string
}

/** Écran d'une entrée de menu déjà câblée (route, garde de profil) mais dont l'écran réel arrive à un lot suivant. */
export function PageBientotDisponible({ titre }: Props) {
  const { t } = useTranslation()
  return <Result icon={<ClockCircleOutlined />} title={titre} subTitle={t('espace.bientotDisponible')} />
}
