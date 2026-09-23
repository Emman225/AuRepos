import { Space, Table, Typography } from 'antd'
import type { TableProps } from 'antd'
import type { ReactNode } from 'react'
import { BoutonsExport } from './BoutonsExport'
import { FiltreDePeriode, type Periode } from './FiltreDePeriode'

interface Props<T extends object> extends Omit<TableProps<T>, 'title'> {
  titre: string
  /** Absent = pas de filtre de période sur cette liste (certaines n'en ont pas de sens, ex. un référentiel). */
  periode?: Periode
  onPeriodeChange?: (periode: Periode) => void
  /** Absent = pas de bouton d'export sur cette liste. */
  urlExport?: string
  filtresExport?: Record<string, unknown>
  /** Filtres propres à l'écran (statut, guichet…), affichés à côté de la période. */
  filtresSupplementaires?: ReactNode
}

/**
 * Gabarit commun à toutes les listes du back office (CdC § 6.8) : filtre de période,
 * filtres propres à l'écran, export Excel / Word / PDF avec les mêmes filtres que l'écran.
 */
export function TableauDeListe<T extends object>({
  titre,
  periode,
  onPeriodeChange,
  urlExport,
  filtresExport,
  filtresSupplementaires,
  ...tableau
}: Props<T>) {
  return (
    <Space orientation="vertical" size="middle" style={{ width: '100%' }}>
      <Space wrap style={{ width: '100%', justifyContent: 'space-between' }}>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {titre}
        </Typography.Title>
        <Space wrap>
          {filtresSupplementaires}
          {periode && onPeriodeChange && <FiltreDePeriode valeur={periode} onChange={onPeriodeChange} />}
          {urlExport && <BoutonsExport url={urlExport} filtres={{ ...filtresExport, ...periode }} nomFichier={titre} />}
        </Space>
      </Space>
      <Table<T> {...tableau} />
    </Space>
  )
}
