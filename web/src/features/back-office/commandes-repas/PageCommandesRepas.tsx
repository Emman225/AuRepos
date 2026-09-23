import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { listerLesLivreurs } from '../livreurs/api'
import { affecterUnLivreurALaCommande, confirmerLaCommande, listerLesCommandesRepas, refuserLaCommande } from './api'
import type { Commande } from './types'

const ETATS = ['demande', 'confirmee', 'en_preparation', 'prete', 'en_livraison', 'livree', 'annulee', 'refusee'] as const

/** Back office › Commandes de repas : suivi, confirmation, affectation d'un livreur, refus motivé (CdC — « Repas et boissons »). */
export function PageCommandesRepas() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [etat, setEtat] = useState<string | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [commandeAAffecter, setCommandeAAffecter] = useState<Commande | null>(null)
  const [commandeARefuser, setCommandeARefuser] = useState<Commande | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const filtres = { etat, page, par_page: parPage }
  const commandes = useQuery({ queryKey: ['backoffice', 'commandes-repas', filtres], queryFn: () => listerLesCommandesRepas(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'commandes-repas'] })

  const confirmer = useMutation({
    mutationFn: (id: number) => confirmerLaCommande(id),
    onSuccess: () => void invalider(),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.commandesRepas')} />

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.commandesRepas.filtreEtat')}
          value={etat}
          onChange={(v) => {
            setEtat(v)
            reinitialiser()
          }}
          options={ETATS.map((e) => ({ value: e, label: t(`backoffice.commandesRepas.etats.${e}`) }))}
        />
      </Space>

      <Table<Commande>
        rowKey="id"
        loading={commandes.isPending}
        dataSource={commandes.data?.elements}
        pagination={propsPagination(commandes.data?.pagination.total)}
        locale={{ emptyText: <EtatVide titre={t('backoffice.commandesRepas.aucune')} /> }}
        columns={[
          { title: t('backoffice.commandesRepas.reference'), dataIndex: 'reference' },
          { title: t('backoffice.commandesRepas.sejour'), dataIndex: 'sejour_reference', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.commandesRepas.restaurateur'), dataIndex: 'restaurateur', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.commandesRepas.montant'), dataIndex: 'montant_total', render: (v: number) => formaterPrix(v) },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'etat',
            render: (v: Commande['etat'], c) => <StatutBadge domaine="commandeRepas" code={v} libelle={c.etat_libelle} />,
          },
          {
            title: '',
            key: 'actions',
            render: (_, c) => (
              <Space>
                {c.etat === 'demande' && (
                  <Button size="small" type="primary" loading={confirmer.isPending} onClick={() => confirmer.mutate(c.id)}>
                    {t('backoffice.commandesRepas.confirmer')}
                  </Button>
                )}
                {c.etat === 'prete' && (
                  <Button size="small" type="primary" onClick={() => setCommandeAAffecter(c)}>
                    {t('backoffice.commandesRepas.affecterLivreur')}
                  </Button>
                )}
                {!['livree', 'annulee', 'refusee'].includes(c.etat) && (
                  <Button size="small" danger onClick={() => setCommandeARefuser(c)}>
                    {t('backoffice.commandesRepas.refuser')}
                  </Button>
                )}
              </Space>
            ),
          },
        ]}
      />

      <ModaleAffectationLivreur
        commande={commandeAAffecter}
        onFermer={() => setCommandeAAffecter(null)}
        onAffecte={() => {
          setCommandeAAffecter(null)
          void invalider()
        }}
      />

      <ModaleRefus
        commande={commandeARefuser}
        onFermer={() => setCommandeARefuser(null)}
        onRefuse={() => {
          setCommandeARefuser(null)
          void invalider()
        }}
      />
    </div>
  )
}

function ModaleAffectationLivreur({ commande, onFermer, onAffecte }: { commande: Commande | null; onFermer: () => void; onAffecte: () => void }) {
  const { t } = useTranslation()
  const [livreurId, setLivreurId] = useState<number | undefined>(undefined)
  const [remuneration, setRemuneration] = useState<number | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const livreurs = useQuery({
    queryKey: ['backoffice', 'livreurs', 'actifs'],
    queryFn: () => listerLesLivreurs({ actif: true, par_page: 100 }),
    enabled: commande !== null,
  })

  const affecter = useMutation({
    mutationFn: () => affecterUnLivreurALaCommande(commande!.id, livreurId!, remuneration ?? 0),
    onSuccess: () => {
      setLivreurId(undefined)
      setRemuneration(null)
      setErreur(null)
      onAffecte()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('backoffice.commandesRepas.affecterLivreur')}
      open={commande !== null}
      onCancel={() => {
        setLivreurId(undefined)
        setRemuneration(null)
        setErreur(null)
        onFermer()
      }}
      onOk={() => affecter.mutate()}
      confirmLoading={affecter.isPending}
      okButtonProps={{ disabled: !livreurId || remuneration === null }}
      okText={t('backoffice.commandesRepas.affecter')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
    >
      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text>{t('backoffice.commandesRepas.livreur')}</Typography.Text>
        <Select
          style={{ width: '100%' }}
          value={livreurId}
          onChange={setLivreurId}
          loading={livreurs.isPending}
          options={livreurs.data?.elements.map((l) => ({ value: l.id, label: l.compte.nom_complet }))}
        />
        <Typography.Text>{t('backoffice.commandesRepas.remunerationLivreur')}</Typography.Text>
        <InputNumber style={{ width: '100%' }} min={0} precision={0} value={remuneration} onChange={setRemuneration} addonAfter="F" />
        {erreur && <Alert type="error" showIcon title={erreur} />}
      </Space>
    </Modal>
  )
}

function ModaleRefus({ commande, onFermer, onRefuse }: { commande: Commande | null; onFermer: () => void; onRefuse: () => void }) {
  const { t } = useTranslation()
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const refuser = useMutation({
    mutationFn: () => refuserLaCommande(commande!.id, motif),
    onSuccess: () => {
      setMotif('')
      setErreur(null)
      onRefuse()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('backoffice.commandesRepas.refuser')}
      open={commande !== null}
      onCancel={() => {
        setMotif('')
        setErreur(null)
        onFermer()
      }}
      onOk={() => refuser.mutate()}
      confirmLoading={refuser.isPending}
      okButtonProps={{ disabled: motif.trim().length < 5, danger: true }}
      okText={t('backoffice.commandesRepas.refuser')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
    >
      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text>{t('backoffice.commandesRepas.motifRefus')}</Typography.Text>
        <Input.TextArea value={motif} onChange={(e) => setMotif(e.target.value)} rows={3} aria-label={t('backoffice.commandesRepas.motifRefus')} />
        {erreur && <Alert type="error" showIcon title={erreur} />}
      </Space>
    </Modal>
  )
}
