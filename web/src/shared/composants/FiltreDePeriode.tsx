import { DatePicker } from 'antd'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { useTranslation } from 'react-i18next'

export interface Periode {
  du: string | null
  au: string | null
}

interface Props {
  valeur: Periode
  onChange: (periode: Periode) => void
}

/** Filtre « Période : du … au … », commun à toutes les listes du back office (CdC § 6.8). */
export function FiltreDePeriode({ valeur, onChange }: Props) {
  const { t } = useTranslation()
  const dates: [Dayjs, Dayjs] | null = valeur.du && valeur.au ? [dayjs(valeur.du), dayjs(valeur.au)] : null

  return (
    <DatePicker.RangePicker
      value={dates}
      onChange={(v) =>
        onChange(
          v && v[0] && v[1] ? { du: v[0].format('YYYY-MM-DD'), au: v[1].format('YYYY-MM-DD') } : { du: null, au: null },
        )
      }
      // Format explicite : ne dépend pas d'un ConfigProvider ambiant, jj/mm/aaaa quelle que soit la langue de l'interface.
      format="DD/MM/YYYY"
      placeholder={[t('listes.periode.du'), t('listes.periode.au')]}
      allowClear
    />
  )
}
