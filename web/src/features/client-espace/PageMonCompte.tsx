import { useQueryClient, useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Card, Skeleton, Space, Tag, Typography, Upload } from 'antd'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { formaterPrix } from '../../shared/format/devise'
import { demanderLeCompteATerme, deposerUnePieceATerme, monCompte, monCompteATermeDetail } from './api'
import type { PieceATerme } from './types'

const TYPES_DE_PIECE: { type: string; cle: string }[] = [
  { type: 'rccm', cle: 'client.compte.pieces.rccm' },
  { type: 'bilan', cle: 'client.compte.pieces.bilan' },
  { type: 'piece_identite', cle: 'client.compte.pieces.pieceIdentite' },
]

/** Espace client › Détail du compte (CdC § 5.3) : coordonnées, régime, pièces, demande de compte à terme. */
export function PageMonCompte() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const compte = useQuery({ queryKey: ['client', 'compte'], queryFn: monCompte })
  const aTerme = useQuery({ queryKey: ['client', 'compte-a-terme-detail'], queryFn: monCompteATermeDetail })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['client', 'compte-a-terme-detail'] })

  const demande = useMutation({ mutationFn: demanderLeCompteATerme, onSuccess: invalider })
  const depot = useMutation({
    mutationFn: ({ type, fichier }: { type: string; fichier: File }) => deposerUnePieceATerme(type, fichier),
    onSuccess: invalider,
  })

  if (compte.isPending || aTerme.isPending) return <Skeleton active paragraph={{ rows: 8 }} />
  if (!compte.data || !aTerme.data) return <Alert type="error" showIcon title={t('tunnel.erreurGenerique')} />

  const c = compte.data
  const dossier = aTerme.data
  const pieceDeType = (type: string): PieceATerme | undefined => dossier.pieces?.find((p) => p.type === type)
  const erreur = demande.error ?? depot.error

  return (
    <div>
      <Typography.Title level={3}>{t('client.menu.compte')}</Typography.Title>

      <Card title={t('client.compte.coordonnees')} style={{ marginBottom: 16 }}>
        <Typography.Paragraph>
          <strong>{c.nom_complet}</strong>
        </Typography.Paragraph>
        <Typography.Paragraph>
          {t('espace.courriel')} : {c.email}
        </Typography.Paragraph>
        <Typography.Paragraph>
          {t('inscription.telephone')} : {c.telephone ?? t('client.compte.nonRenseigne')}
        </Typography.Paragraph>
      </Card>

      <Card title={t('client.compte.regime')} style={{ marginBottom: 16 }}>
        <Space orientation="vertical">
          <Tag color="blue">{c.nature_libelle}</Tag>
          {c.raison_sociale && (
            <Typography.Paragraph style={{ margin: 0 }}>
              {t('client.compte.raisonSociale')} : {c.raison_sociale}
            </Typography.Paragraph>
          )}
          {c.ncc && (
            <Typography.Paragraph style={{ margin: 0 }}>
              {t('client.compte.ncc')} : {c.ncc}
            </Typography.Paragraph>
          )}
        </Space>
      </Card>

      <Card title={t('client.compte.aTerme.titre')}>
        {erreur && (
          <Alert
            style={{ marginBottom: 16 }}
            type="error"
            showIcon
            title={erreur instanceof ErreurApi ? erreur.message : t('tunnel.erreurGenerique')}
          />
        )}

        <Tag color={dossier.statut === 'acceptee' ? 'green' : dossier.statut === 'refusee' ? 'red' : 'gold'}>
          {dossier.statut_libelle}
        </Tag>

        {dossier.statut === 'acceptee' && (
          <Typography.Paragraph style={{ marginTop: 12 }}>
            {t('client.compte.aTerme.plafond')} : {formaterPrix(dossier.plafond_credit)} —{' '}
            {t('client.compte.aTerme.encours')} : {formaterPrix(dossier.encours ?? 0)}
          </Typography.Paragraph>
        )}

        {dossier.statut === 'refusee' && dossier.motif_refus && (
          <Typography.Paragraph style={{ marginTop: 12 }} type="danger">
            {dossier.motif_refus}
          </Typography.Paragraph>
        )}

        {(dossier.statut === 'aucune' || dossier.statut === 'refusee') && (
          <Button style={{ marginTop: 12 }} type="primary" loading={demande.isPending} onClick={() => demande.mutate()}>
            {t('client.compte.aTerme.demander')}
          </Button>
        )}

        {dossier.statut === 'en_attente' && (
          <div style={{ marginTop: 16 }}>
            <Typography.Paragraph type="secondary">{t('client.compte.aTerme.piecesAttendues')}</Typography.Paragraph>
            <Space orientation="vertical" style={{ width: '100%' }}>
              {TYPES_DE_PIECE.map(({ type, cle }) => {
                const piece = pieceDeType(type)
                return (
                  <Space key={type} align="center">
                    <span style={{ minWidth: 160, display: 'inline-block' }}>{t(cle)}</span>
                    {piece ? (
                      <Tag color={piece.statut === 'validee' ? 'green' : piece.statut === 'refusee' ? 'red' : 'gold'}>
                        {piece.statut_libelle}
                      </Tag>
                    ) : (
                      <Upload
                        showUploadList={false}
                        beforeUpload={(fichier) => {
                          depot.mutate({ type, fichier })
                          return false
                        }}
                      >
                        <Button size="small" loading={depot.isPending}>
                          {t('client.compte.aTerme.deposer')}
                        </Button>
                      </Upload>
                    )}
                  </Space>
                )
              })}
            </Space>
          </div>
        )}
      </Card>
    </div>
  )
}
