import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { useSession } from '../../auth/session'
import {
  emettreUnAvoir,
  genererUneFacture,
  listerLesFactures,
  ouvrirLaFacturePdf,
  STATUTS_TRANSMISSION,
  transmettreLaFacture,
  TYPES_DE_FACTURE,
} from './api'
import type { Facture, StatutDeTransmissionFne, TypeDeFacture } from './types'

/** Back office › Factures normalisées électroniques (FNE, DGI — CdC § 9.4). */
export function PageFactures() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const estAdministrateur = useSession((s) => s.utilisateur?.profil === 'administrateur' || s.utilisateur?.profil === 'super_administrateur')

  const [statut, setStatut] = useState<StatutDeTransmissionFne | undefined>(undefined)
  const [type, setType] = useState<TypeDeFacture | undefined>(undefined)
  const { parPage, page, reinitialiser, propsPagination } = usePagination()
  const [nouvelleOuverte, setNouvelleOuverte] = useState(false)
  const [factureOuverte, setFactureOuverte] = useState<Facture | null>(null)

  const filtres = { statut_transmission: statut, type, page, par_page: parPage }
  const factures = useQuery({ queryKey: ['backoffice', 'factures', filtres], queryFn: () => listerLesFactures(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'factures'] })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.factures.titre')}
        actions={
          <>
            <BoutonsExport
              url="/backoffice/factures/export"
              filtres={{ statut_transmission: statut, type }}
              nomFichier="Factures"
            />
            <Button type="primary" onClick={() => setNouvelleOuverte(true)}>
              {t('backoffice.factures.nouvelle')}
            </Button>
          </>
        }
      />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 220 }}
          allowClear
          placeholder={t('backoffice.factures.statut')}
          value={statut}
          onChange={(v) => {
            setStatut(v)
            reinitialiser()
          }}
          options={STATUTS_TRANSMISSION.map((s) => ({ value: s, label: t(`backoffice.factures.statuts.${s}`) }))}
        />
        <Select
          style={{ width: 180 }}
          allowClear
          placeholder={t('backoffice.factures.type')}
          value={type}
          onChange={(v) => {
            setType(v)
            reinitialiser()
          }}
          options={TYPES_DE_FACTURE.map((v) => ({ value: v, label: t(`backoffice.factures.types.${v}`) }))}
        />
      </Space>

      <Table<Facture>
        rowKey="id"
        size="small"
        loading={factures.isPending}
        dataSource={factures.data?.elements}
        pagination={propsPagination(factures.data?.pagination.total)}
        onRow={(f) => ({ onClick: () => setFactureOuverte(f), style: { cursor: 'pointer' } })}
        columns={[
          { title: t('backoffice.factures.numero'), dataIndex: 'numero' },
          { title: t('backoffice.factures.type'), dataIndex: 'type_libelle' },
          { title: t('backoffice.factures.sejour'), key: 'sejour', render: (_, f) => f.sejour?.reference ?? '—' },
          { title: t('backoffice.clients.nom'), key: 'client', render: (_, f) => f.client?.nom ?? '—' },
          { title: t('backoffice.factures.montantTtc'), dataIndex: 'montant_ttc', render: (v: number) => formaterPrix(Math.abs(v)) },
          {
            title: t('backoffice.factures.statut'),
            dataIndex: 'statut_transmission_libelle',
            render: (v: string, f) => <StatutBadge domaine="transmissionFne" code={f.statut_transmission} libelle={v} />,
          },
          { title: t('backoffice.factures.refDgi'), dataIndex: 'reference_dgi', render: (v: string | null) => v ?? '—' },
        ]}
      />

      <Modal title={t('backoffice.factures.nouvelle')} open={nouvelleOuverte} onCancel={() => setNouvelleOuverte(false)} footer={null} destroyOnHidden>
        <FormulaireNouvelleFacture
          onGeneree={() => {
            setNouvelleOuverte(false)
            void invalider()
          }}
        />
      </Modal>

      <Modal
        title={factureOuverte?.numero}
        open={factureOuverte !== null}
        onCancel={() => setFactureOuverte(null)}
        footer={null}
        destroyOnHidden
        width={640}
      >
        {factureOuverte && (
          <DetailFacture
            facture={factureOuverte}
            estAdministrateur={estAdministrateur}
            onMiseAJour={(f) => {
              setFactureOuverte(f)
              void invalider()
            }}
          />
        )}
      </Modal>
    </div>
  )
}

function FormulaireNouvelleFacture({ onGeneree }: { onGeneree: () => void }) {
  const { t } = useTranslation()
  const [sejourId, setSejourId] = useState<number | null>(null)
  const [type, setType] = useState<'proforma' | 'facture'>('proforma')
  const [erreur, setErreur] = useState<string | null>(null)

  const generer = useMutation({
    mutationFn: () => genererUneFacture(sejourId!, type),
    onSuccess: onGeneree,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.factures.sejourId')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} value={sejourId} onChange={setSejourId} />
      <Typography.Text>{t('backoffice.factures.type')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={type}
        onChange={setType}
        options={[
          { value: 'proforma', label: t('backoffice.factures.types.proforma') },
          { value: 'facture', label: t('backoffice.factures.types.facture') },
        ]}
      />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={generer.isPending} disabled={!sejourId} onClick={() => generer.mutate()}>
        {t('backoffice.factures.generer')}
      </Button>
    </Space>
  )
}

function DetailFacture({
  facture,
  estAdministrateur,
  onMiseAJour,
}: {
  facture: Facture
  estAdministrateur: boolean
  onMiseAJour: (f: Facture) => void
}) {
  const { t } = useTranslation()
  const [avoirOuvert, setAvoirOuvert] = useState(false)
  const [motifAvoir, setMotifAvoir] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const surErreur = (e: unknown) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))

  const pdf = useMutation({ mutationFn: () => ouvrirLaFacturePdf(facture.id), onError: surErreur })
  const transmettre = useMutation({ mutationFn: () => transmettreLaFacture(facture.id), onSuccess: onMiseAJour, onError: surErreur })
  const avoir = useMutation({
    mutationFn: () => emettreUnAvoir(facture.id, motifAvoir),
    onSuccess: (f) => {
      onMiseAJour(f)
      setAvoirOuvert(false)
      setMotifAvoir('')
    },
    onError: surErreur,
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }} size="middle">
      <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
        {facture.type_libelle} — {facture.sejour?.reference} — {facture.client?.nom}
      </Typography.Paragraph>

      {erreur && <Alert type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}
      {facture.motif_refus_dgi && <Alert type="warning" showIcon title={facture.motif_refus_dgi} />}

      <Table
        size="small"
        rowKey="description"
        dataSource={facture.lignes}
        pagination={false}
        columns={[
          { title: t('backoffice.factures.designation'), dataIndex: 'description' },
          { title: t('backoffice.factures.quantite'), dataIndex: 'quantity' },
          { title: t('backoffice.factures.montantHt'), dataIndex: 'amount', render: (v: number) => formaterPrix(v) },
        ]}
      />

      <Typography.Paragraph style={{ margin: 0 }}>
        {t('backoffice.factures.montantTtc')} : <strong>{formaterPrix(Math.abs(facture.montant_ttc))}</strong>
      </Typography.Paragraph>

      {facture.reference_dgi && (
        <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
          {t('backoffice.factures.refDgi')} : {facture.reference_dgi}
          {facture.solde_stickers !== null && ` — ${t('backoffice.factures.soldeStickers')} : ${facture.solde_stickers}`}
        </Typography.Paragraph>
      )}

      <Space wrap>
        <Button loading={pdf.isPending} onClick={() => pdf.mutate()}>
          {t('backoffice.factures.voirLePdf')}
        </Button>
        {estAdministrateur && facture.statut_transmission !== 'transmise' && facture.type !== 'avoir' && (
          <Button type="primary" loading={transmettre.isPending} onClick={() => transmettre.mutate()}>
            {t('backoffice.factures.transmettre')}
          </Button>
        )}
        {estAdministrateur && facture.statut_transmission === 'transmise' && facture.type === 'facture' && (
          <Button danger onClick={() => setAvoirOuvert(true)}>
            {t('backoffice.factures.emettreUnAvoir')}
          </Button>
        )}
      </Space>

      <Modal title={t('backoffice.factures.emettreUnAvoir')} open={avoirOuvert} onCancel={() => setAvoirOuvert(false)} footer={null} destroyOnHidden>
        <Space orientation="vertical" style={{ width: '100%' }}>
          <Typography.Text>{t('backoffice.factures.motifAvoir')}</Typography.Text>
          <Input.TextArea value={motifAvoir} onChange={(e) => setMotifAvoir(e.target.value)} rows={3} />
          <Button danger type="primary" loading={avoir.isPending} disabled={motifAvoir.trim().length < 5} onClick={() => avoir.mutate()}>
            {t('backoffice.factures.emettreUnAvoir')}
          </Button>
        </Space>
      </Modal>
    </Space>
  )
}
