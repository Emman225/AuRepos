import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Table, Typography, Upload } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import {
  deciderDUnePieceATerme,
  deciderDuCompteATerme,
  deposerUnePieceATerme,
  listerLesComptesATerme,
  listerLesPiecesATerme,
} from './api'
import type { ClientATerme, PieceJustificative, StatutDemandeATerme, TypeDePiece } from './types'

const TYPES_DE_PIECE: TypeDePiece[] = ['rccm', 'bilan', 'piece_identite', 'titre_propriete', 'bail', 'rib', 'mandat', 'dfe', 'attestation_regime', 'autre']

/** Back office › Clients › Demandes de compte à terme (CdC § 5.1, 5.3). */
export function OngletComptesATerme() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<StatutDemandeATerme>('en_attente')
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [dossierOuvert, setDossierOuvert] = useState<ClientATerme | null>(null)

  const demandes = useQuery({
    queryKey: ['backoffice', 'clients-a-terme', statut, page],
    queryFn: () => listerLesComptesATerme({ statut, page, par_page: parPage }),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'clients-a-terme'] })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Space wrap>
          <Select
            style={{ width: 240 }}
            value={statut}
            onChange={(v) => {
              setStatut(v)
              reinitialiser()
            }}
            options={(['en_attente', 'acceptee', 'refusee', 'aucune'] as StatutDemandeATerme[]).map((s) => ({
              value: s,
              label: t(`backoffice.clients.aTermeStatuts.${s}`),
            }))}
          />
        </Space>
        <BoutonsExport url="/backoffice/clients-a-terme/export" filtres={{ statut }} nomFichier="ClientsATerme" />
      </div>

      <Table<ClientATerme>
        rowKey="client_id"
        size="small"
        loading={demandes.isPending}
        dataSource={demandes.data?.elements}
        pagination={propsPagination(demandes.data?.pagination.total)}
        locale={{ emptyText: t('backoffice.clients.aucuneDemande') }}
        onRow={(d) => ({ onClick: () => setDossierOuvert(d), style: { cursor: 'pointer' } })}
        columns={[
          { title: t('backoffice.clients.nom'), key: 'nom', render: (_, d) => d.utilisateur?.nom ?? `#${d.client_id}` },
          { title: t('backoffice.clients.nature'), dataIndex: 'nature_libelle' },
          { title: t('backoffice.proprietaires.raisonSociale'), dataIndex: 'raison_sociale', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.clients.aTerme.demandeLe'), dataIndex: 'demande_le', render: (v: string | null) => v ?? '—' },
          {
            title: t('backoffice.clients.aTerme.plafondCredit'),
            dataIndex: 'plafond_credit',
            render: (v: number | null) => (v !== null ? formaterPrix(v) : '—'),
          },
          {
            title: t('backoffice.clients.aTerme.encours'),
            dataIndex: 'encours',
            render: (v: number | null) => (v !== null ? formaterPrix(v) : '—'),
          },
          { title: t('backoffice.clients.statut'), dataIndex: 'statut_libelle', render: (v: string, d) => <StatutBadge domaine="demandeATerme" code={d.statut} libelle={v} /> },
        ]}
      />

      <Modal
        title={dossierOuvert?.utilisateur?.nom ?? t('backoffice.clients.aTerme.dossier')}
        open={dossierOuvert !== null}
        onCancel={() => setDossierOuvert(null)}
        footer={null}
        destroyOnHidden
        width={640}
      >
        {dossierOuvert && (
          <DossierATerme
            dossier={dossierOuvert}
            onDecide={() => {
              setDossierOuvert(null)
              void invalider()
            }}
          />
        )}
      </Modal>
    </div>
  )
}

function DossierATerme({ dossier, onDecide }: { dossier: ClientATerme; onDecide: () => void }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [plafondCredit, setPlafondCredit] = useState<number | null>(0)
  const [motifRefus, setMotifRefus] = useState('')
  const [typeADeposer, setTypeADeposer] = useState<TypeDePiece>('rccm')
  const [erreur, setErreur] = useState<string | null>(null)

  const surErreur = (e: unknown) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))

  // Les routes `/backoffice/clients-a-terme/{client}` attendent l'identifiant du COMPTE
  // (User), pas celui de la fiche `clients` (`dossier.client_id`) — deux nombres différents.
  const idUtilisateur = dossier.utilisateur?.id ?? dossier.client_id

  const pieces = useQuery({
    queryKey: ['backoffice', 'clients-a-terme', idUtilisateur, 'pieces'],
    queryFn: () => listerLesPiecesATerme(idUtilisateur),
  })
  const invaliderPieces = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'clients-a-terme', idUtilisateur, 'pieces'] })

  const deposer = useMutation({
    mutationFn: (fichier: File) => deposerUnePieceATerme(idUtilisateur, typeADeposer, fichier),
    onSuccess: invaliderPieces,
    onError: surErreur,
  })
  const deciderPiece = useMutation({
    mutationFn: ({ pieceId, decision }: { pieceId: number; decision: 'valider' | 'refuser' }) =>
      deciderDUnePieceATerme(idUtilisateur, pieceId, decision, decision === 'refuser' ? 'Pièce non conforme.' : undefined),
    onSuccess: invaliderPieces,
    onError: surErreur,
  })
  const decider = useMutation({
    mutationFn: (decision: 'accepter' | 'refuser') =>
      deciderDuCompteATerme(idUtilisateur, decision, decision === 'accepter' ? (plafondCredit ?? 0) : undefined, decision === 'refuser' ? motifRefus : undefined),
    onSuccess: onDecide,
    onError: surErreur,
  })

  const typesDeposes = new Set((pieces.data ?? []).map((p) => p.type))

  return (
    <Space orientation="vertical" style={{ width: '100%' }} size="middle">
      <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
        {dossier.utilisateur?.email} — {dossier.nature_libelle}
        {dossier.raison_sociale ? ` — ${dossier.raison_sociale}` : ''}
      </Typography.Paragraph>

      {erreur && <Alert type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      <div>
        <Typography.Text strong>{t('backoffice.proprietaires.pieces.titre')}</Typography.Text>
        <Table<PieceJustificative>
          rowKey="id"
          size="small"
          style={{ marginTop: 8 }}
          dataSource={pieces.data}
          loading={pieces.isPending}
          pagination={false}
          columns={[
            { title: t('backoffice.proprietaires.pieces.type'), dataIndex: 'type_libelle' },
            { title: t('backoffice.proprietaires.pieces.nom'), dataIndex: 'nom_original' },
            { title: t('backoffice.proprietaires.pieces.statut'), dataIndex: 'statut_libelle', render: (v: string, p) => <StatutBadge domaine="piece" code={p.statut} libelle={v} /> },
            {
              title: '',
              key: 'actions',
              render: (_, p) =>
                p.statut === 'en_attente' && (
                  <Space>
                    <Button size="small" onClick={() => deciderPiece.mutate({ pieceId: p.id, decision: 'valider' })}>
                      {t('backoffice.proprietaires.pieces.valider')}
                    </Button>
                    <Button size="small" danger onClick={() => deciderPiece.mutate({ pieceId: p.id, decision: 'refuser' })}>
                      {t('backoffice.proprietaires.pieces.refuser')}
                    </Button>
                  </Space>
                ),
            },
          ]}
        />
        <Space style={{ marginTop: 12 }}>
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
      </div>

      {dossier.statut === 'en_attente' && (
        <div>
          <Typography.Text strong>{t('backoffice.clients.aTerme.decision')}</Typography.Text>
          <Space wrap style={{ marginTop: 8 }} align="start">
            <Space orientation="vertical" size={4}>
              <InputNumber min={0} step={50000} value={plafondCredit} onChange={setPlafondCredit} placeholder={t('backoffice.clients.aTerme.plafondCredit')} />
              <Button type="primary" loading={decider.isPending} onClick={() => decider.mutate('accepter')}>
                {t('backoffice.clients.aTerme.accepter')}
              </Button>
            </Space>
            <Space orientation="vertical" size={4}>
              <Input placeholder={t('backoffice.clients.aTerme.motifRefus')} value={motifRefus} onChange={(e) => setMotifRefus(e.target.value)} style={{ width: 220 }} />
              <Button danger loading={decider.isPending} disabled={motifRefus.trim().length < 5} onClick={() => decider.mutate('refuser')}>
                {t('backoffice.clients.aTerme.refuser')}
              </Button>
            </Space>
          </Space>
        </div>
      )}

      {dossier.statut === 'refusee' && dossier.motif_refus && (
        <Typography.Paragraph type="danger" style={{ margin: 0 }}>
          {dossier.motif_refus}
        </Typography.Paragraph>
      )}
    </Space>
  )
}
