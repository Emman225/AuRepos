import { useQueryClient, useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Card, Select, Skeleton, Space, Table, Tag, Typography, Upload } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi } from '../../../shared/api/client'
import { useConfirmerAction } from '../../../shared/composants/confirmer'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { listerLesResidences } from '../catalogue/api'
import type { Residence } from '../catalogue/types'
import { afficherLeProprietaire, deciderDUnePiece, deposerUnePiece, listerLesPieces } from './api'
import { FormulaireProprietaire } from './FormulaireProprietaire'
import type { PieceJustificative, TypeDePiece } from './types'

const TYPES_DE_PIECE: TypeDePiece[] = ['piece_identite', 'titre_propriete', 'bail', 'rib', 'mandat', 'dfe', 'attestation_regime', 'rccm', 'bilan', 'autre']

/** Back office › Détail d'un propriétaire (CdC § 7.2) : dossier, mandat, pièces, biens confiés. */
export function PageDetailProprietaire() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const idNombre = Number(id)
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [editionOuverte, setEditionOuverte] = useState(false)
  const [typeADeposer, setTypeADeposer] = useState<TypeDePiece>('piece_identite')
  const [erreur, setErreur] = useState<string | null>(null)

  const cle = ['backoffice', 'proprietaires', idNombre] as const
  const proprietaire = useQuery({ queryKey: cle, queryFn: () => afficherLeProprietaire(idNombre), enabled: Number.isFinite(idNombre) })
  const pieces = useQuery({ queryKey: [...cle, 'pieces'], queryFn: () => listerLesPieces(idNombre), enabled: Number.isFinite(idNombre) })
  const residences = useQuery({
    queryKey: ['backoffice', 'residences', { proprietaire_id: idNombre }],
    queryFn: () => listerLesResidences({ proprietaire_id: idNombre }),
    enabled: Number.isFinite(idNombre),
  })

  const surErreur = (e: unknown) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
  const invaliderPieces = () => queryClient.invalidateQueries({ queryKey: [...cle, 'pieces'] })

  const deposer = useMutation({ mutationFn: (fichier: File) => deposerUnePiece(idNombre, typeADeposer, fichier), onSuccess: invaliderPieces, onError: surErreur })
  const decider = useMutation({
    mutationFn: ({ pieceId, decision }: { pieceId: number; decision: 'valider' | 'refuser' }) =>
      deciderDUnePiece(idNombre, pieceId, decision, decision === 'refuser' ? 'Pièce non conforme.' : undefined),
    onSuccess: invaliderPieces,
    onError: surErreur,
  })

  if (proprietaire.isPending) return <Skeleton active paragraph={{ rows: 10 }} />
  if (!proprietaire.data) return <Alert type="error" showIcon title={t('client.sejours.introuvable')} />

  const p = proprietaire.data
  const typesDeposes = new Set((pieces.data ?? []).map((piece) => piece.type))

  return (
    <div>
      <Link to="..">{t('client.detail.retourALaListe')}</Link>
      <EnTeteDePage titre={p.nom_affiche} actions={<Button onClick={() => setEditionOuverte(true)}>{t('backoffice.proprietaires.modifier')}</Button>} />
      <Typography.Paragraph>
        {p.compte.email}
        {p.compte.telephone && ` — ${p.compte.telephone}`}
      </Typography.Paragraph>
      <Space wrap>
        <Tag color="default">{p.nature_libelle}</Tag>
        <StatutBadge domaine="actif" code={p.dossier_complet ? 'actif' : 'inactif'} libelle={p.dossier_complet ? t('backoffice.proprietaires.complet') : t('backoffice.proprietaires.incomplet')} />
        {p.interne && <Tag color="blue">{t('backoffice.proprietaires.interne')}</Tag>}
      </Space>

      {!p.dossier_complet && p.elements_manquants.length > 0 && (
        <Alert
          style={{ marginTop: 16 }}
          type="warning"
          showIcon
          title={t('backoffice.proprietaires.elementsManquants')}
          description={
            <ul style={{ margin: 0, paddingInlineStart: 20 }}>
              {p.elements_manquants.map((e) => (
                <li key={e}>{e}</li>
              ))}
            </ul>
          }
        />
      )}

      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}

      <Card title={t('backoffice.proprietaires.mandat.titre')} style={{ marginTop: 16 }}>
        <Typography.Paragraph style={{ margin: 0 }}>
          {p.mandat.mode_remuneration_libelle}
          {p.mandat.taux_commission !== null && ` (${p.mandat.taux_commission} %)`}
        </Typography.Paragraph>
        <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
          {p.mandat.signe_le ? t('backoffice.proprietaires.mandat.signeLeValeur', { date: p.mandat.signe_le }) : t('backoffice.proprietaires.mandat.nonSigne')}
        </Typography.Paragraph>
        <Typography.Paragraph style={{ marginTop: 12, marginBottom: 0 }}>
          {t('backoffice.proprietaires.retenueALaSource')} : {p.retenue_a_la_source.taux} % — {p.retenue_a_la_source.motif}
        </Typography.Paragraph>
      </Card>

      <Card title={t('backoffice.proprietaires.pieces.titre')} style={{ marginTop: 16 }}>
        <Table<PieceJustificative>
          rowKey="id"
          size="small"
          dataSource={pieces.data}
          loading={pieces.isPending}
          pagination={false}
          columns={[
            { title: t('backoffice.proprietaires.pieces.type'), dataIndex: 'type_libelle' },
            { title: t('backoffice.proprietaires.pieces.nom'), dataIndex: 'nom_original' },
            { title: t('backoffice.proprietaires.pieces.statut'), dataIndex: 'statut_libelle', render: (v: string, piece) => <StatutBadge domaine="piece" code={piece.statut} libelle={v} /> },
            {
              title: '',
              key: 'actions',
              render: (_, piece) =>
                piece.statut === 'en_attente' && (
                  <Space>
                    <Button size="small" onClick={() => decider.mutate({ pieceId: piece.id, decision: 'valider' })}>
                      {t('backoffice.proprietaires.pieces.valider')}
                    </Button>
                    <Button
                      size="small"
                      danger
                      onClick={async () => {
                        const confirme = await confirmer({ titre: t('backoffice.proprietaires.pieces.confirmerRefus'), danger: true })
                        if (confirme) decider.mutate({ pieceId: piece.id, decision: 'refuser' })
                      }}
                    >
                      {t('backoffice.proprietaires.pieces.refuser')}
                    </Button>
                  </Space>
                ),
            },
          ]}
        />

        <Space style={{ marginTop: 16 }}>
          <Select
            style={{ width: 220 }}
            value={typeADeposer}
            onChange={setTypeADeposer}
            options={TYPES_DE_PIECE.map((type) => ({ value: type, label: t(`backoffice.proprietaires.pieces.types.${type}`), disabled: typesDeposes.has(type) }))}
          />
          <Upload
            showUploadList={false}
            accept="application/pdf,image/jpeg,image/png"
            beforeUpload={(fichier) => {
              deposer.mutate(fichier)
              return false
            }}
          >
            <Button loading={deposer.isPending}>{t('backoffice.proprietaires.pieces.deposer')}</Button>
          </Upload>
        </Space>
      </Card>

      <Card title={t('backoffice.proprietaires.residencesConfiees')} style={{ marginTop: 16 }}>
        <Table<Residence>
          rowKey="id"
          size="small"
          dataSource={residences.data?.elements}
          loading={residences.isPending}
          pagination={false}
          columns={[
            { title: t('backoffice.catalogue.residence.nom'), dataIndex: 'nom', render: (_, r) => <Link to={`/admin/catalogue/${r.id}`}>{r.nom}</Link> },
            { title: t('backoffice.catalogue.residence.lieu'), dataIndex: ['lieu', 'libelle'] },
            { title: t('backoffice.catalogue.residence.logements'), dataIndex: 'nombre_logements', render: (v: number | undefined) => v ?? 0 },
          ]}
        />
      </Card>

      <FormulaireProprietaire
        ouvert={editionOuverte}
        proprietaire={p}
        onFermer={() => setEditionOuverte(false)}
        onEnregistre={() => {
          setEditionOuverte(false)
          void queryClient.invalidateQueries({ queryKey: cle })
        }}
      />
    </div>
  )
}
