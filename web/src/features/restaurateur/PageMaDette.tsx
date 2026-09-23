import { useQuery } from '@tanstack/react-query'
import { Skeleton, Table, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { maDette } from './api'
import type { DetteRestaurateur } from './types'

/** Espace restaurateur › SA dette et SES paiements déjà reçus (CdC — espace restaurateur : « dette, paiements »). */
export function PageMaDette() {
  const { t } = useTranslation()
  const dette = useQuery({ queryKey: ['restaurateur', 'dette'], queryFn: maDette })

  return (
    <div>
      <EnTeteDePage titre={t('restaurateur.menu.dette')} />

      {dette.isPending ? (
        <Skeleton active paragraph={{ rows: 4 }} />
      ) : (
        dette.data && (
          <>
            <div style={{ display: 'flex', gap: 32, marginBottom: 24 }}>
              <div>
                <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                  {t('restaurateur.dette.du')}
                </Typography.Text>
                <Typography.Text strong style={{ fontSize: 26, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(dette.data.du)}
                </Typography.Text>
              </div>
              <div>
                <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                  {t('restaurateur.dette.dejaVerse')}
                </Typography.Text>
                <Typography.Text strong style={{ fontSize: 20, fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(dette.data.deja_verse)}
                </Typography.Text>
              </div>
            </div>

            <Typography.Title level={5}>{t('restaurateur.dette.paiements')}</Typography.Title>
            <Table<DetteRestaurateur['paiements'][number]>
              rowKey="reference"
              dataSource={dette.data.paiements}
              pagination={false}
              locale={{ emptyText: <EtatVide titre={t('restaurateur.dette.aucunPaiement')} /> }}
              columns={[
                { title: t('backoffice.caisse.registre.reference'), dataIndex: 'reference' },
                { title: t('backoffice.caisse.avances.date'), dataIndex: 'date', render: (v: string | null) => v ?? '—' },
                { title: t('backoffice.caisse.registre.montant'), dataIndex: 'montant', render: (v: number) => formaterPrix(v) },
                { title: t('backoffice.caisse.registre.mode'), dataIndex: 'mode' },
              ]}
            />
          </>
        )
      )}
    </div>
  )
}
