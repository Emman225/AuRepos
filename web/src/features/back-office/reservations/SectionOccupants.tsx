import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Space, Table, Typography, Upload } from 'antd'
import { useTranslation } from 'react-i18next'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { listerLesOccupants, televerserUnePieceOccupant } from './api'
import type { OccupantDeSejour } from './types'

interface Props {
  sejourId: number
}

/** Fiche de police (CdC § 6.1, § 11) : occupants du séjour, pièce jointe par occupant — jamais le numéro de pièce lui-même. */
export function SectionOccupants({ sejourId }: Props) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const occupants = useQuery({
    queryKey: ['backoffice', 'sejours', sejourId, 'occupants'],
    queryFn: () => listerLesOccupants(sejourId),
  })

  if (!Array.isArray(occupants.data) || occupants.data.length === 0) return null

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'sejours', sejourId, 'occupants'] })

  return (
    <Card title={t('backoffice.reservations.occupantsTitre')} style={{ marginBottom: 24 }}>
      <Table<OccupantDeSejour>
        rowKey="id"
        size="small"
        loading={occupants.isPending}
        dataSource={occupants.data}
        pagination={false}
        columns={[
          {
            title: t('backoffice.reservations.occupants.nom'),
            render: (_, o) => `${o.nom} ${o.prenoms ?? ''}${o.enfant ? ` (${t('backoffice.reservations.occupants.enfant')})` : ''}`,
          },
          { title: t('backoffice.reservations.occupants.typePiece'), dataIndex: 'type_piece', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.reservations.occupants.telephone'), dataIndex: 'telephone', render: (v: string | null) => v ?? '—' },
          {
            title: t('backoffice.reservations.occupants.piece'),
            render: (_, o) =>
              o.pieces.length > 0 ? (
                <Space direction="vertical" size={2}>
                  {o.pieces.map((p) => (
                    <Space key={p.id} size={6}>
                      <Typography.Text style={{ fontSize: 12 }}>{p.nom_original}</Typography.Text>
                      <StatutBadge domaine="piece" code={p.statut} libelle={t(`backoffice.reservations.occupants.statutsPiece.${p.statut}`, p.statut)} />
                    </Space>
                  ))}
                </Space>
              ) : (
                <Typography.Text type="secondary">{t('backoffice.reservations.occupants.aucunePiece')}</Typography.Text>
              ),
          },
          {
            title: '',
            key: 'actions',
            render: (_, o) => (
              <Upload
                showUploadList={false}
                accept="image/jpeg,image/png,application/pdf"
                beforeUpload={(fichier) => {
                  void televerserUnePieceOccupant(sejourId, o.id, fichier).then(() => invalider())
                  return false
                }}
              >
                <Button size="small">{t('backoffice.reservations.occupants.televerserPiece')}</Button>
              </Upload>
            ),
          },
        ]}
      />
    </Card>
  )
}
