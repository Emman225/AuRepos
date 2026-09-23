import { Segmented } from 'antd'
import { useTranslation } from 'react-i18next'
import { definirLangue, LANGUES, type Langue } from './langues'

/** Bascule fr / en visible sur le site public et dans l'espace client (CdC § 13.2). */
export function SelecteurDeLangue() {
  const { i18n } = useTranslation()

  return (
    <Segmented
      value={i18n.language}
      onChange={(valeur) => void definirLangue(valeur as Langue)}
      options={LANGUES.map((langue) => ({ label: langue.toUpperCase(), value: langue }))}
      size="small"
    />
  )
}
