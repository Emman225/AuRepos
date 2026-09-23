import { FileExcelOutlined, FilePdfOutlined, FileWordOutlined } from '@ant-design/icons'
import { App, Button, Space, Tooltip } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi, telechargerExport } from '../api/client'

interface Props {
  /** Adresse de l'export (…/export) ; les filtres de l'écran, période comprise, sont déjà dans `filtres`. */
  url: string
  filtres: Record<string, unknown>
  nomFichier: string
}

const FORMATS = [
  { format: 'xlsx' as const, icone: <FileExcelOutlined />, couleur: '#1D6F42' },
  { format: 'docx' as const, icone: <FileWordOutlined />, couleur: '#2B579A' },
  { format: 'pdf' as const, icone: <FilePdfOutlined />, couleur: '#B3261E' },
]

/** Trois boutons d'export (Excel, Word, PDF), mêmes filtres que l'écran (CdC § 6.8). */
export function BoutonsExport({ url, filtres, nomFichier }: Props) {
  const { t } = useTranslation()
  const { message } = App.useApp()
  const [enCours, setEnCours] = useState<string | null>(null)

  const exporter = async (format: 'xlsx' | 'docx' | 'pdf'): Promise<void> => {
    setEnCours(format)
    try {
      await telechargerExport(url, filtres, format, nomFichier)
    } catch (e) {
      void message.error(e instanceof ErreurApi ? e.message : t('listes.export.echec'))
    } finally {
      setEnCours(null)
    }
  }

  return (
    <Space size={4}>
      {FORMATS.map(({ format, icone, couleur }) => {
        const libelle = t(`listes.export.${format === 'xlsx' ? 'excel' : format === 'docx' ? 'word' : 'pdf'}`)
        return (
          <Tooltip key={format} title={libelle}>
            <Button
              shape="circle"
              icon={icone}
              aria-label={libelle}
              loading={enCours === format}
              disabled={enCours !== null && enCours !== format}
              onClick={() => void exporter(format)}
              style={{ color: couleur, borderColor: couleur }}
            />
          </Tooltip>
        )
      })}
    </Space>
  )
}
