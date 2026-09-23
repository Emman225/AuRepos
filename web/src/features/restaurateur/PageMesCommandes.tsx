import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, InputNumber, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { Modal } from '../../shared/composants/PopupModal'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { formaterPrix } from '../../shared/format/devise'
import { demarrerLaPreparation, marquerLaCommandePrete, mesCommandes } from './api'
import type { Commande } from './types'

/** Espace restaurateur › SES commandes : démarrer la préparation, marquer prête avec le bon de préparation. */
export function PageMesCommandes() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [erreur, setErreur] = useState<string | null>(null)
  const [commandeAPreparer, setCommandeAPreparer] = useState<Commande | null>(null)

  const commandes = useQuery({ queryKey: ['restaurateur', 'commandes'], queryFn: mesCommandes })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['restaurateur', 'commandes'] })

  const demarrer = useMutation({
    mutationFn: (id: number) => demarrerLaPreparation(id),
    onSuccess: () => void invalider(),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      <EnTeteDePage titre={t('restaurateur.menu.commandes')} />

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      <Table<Commande>
        rowKey="id"
        loading={commandes.isPending}
        dataSource={commandes.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('backoffice.commandesRepas.aucune')} /> }}
        columns={[
          { title: t('backoffice.commandesRepas.reference'), dataIndex: 'reference' },
          { title: t('client.sejours.reference'), dataIndex: 'sejour_reference', render: (v: string | null) => v ?? '—' },
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
                {c.etat === 'confirmee' && (
                  <Button size="small" type="primary" loading={demarrer.isPending} onClick={() => demarrer.mutate(c.id)}>
                    {t('restaurateur.commandes.demarrerLaPreparation')}
                  </Button>
                )}
                {c.etat === 'en_preparation' && (
                  <Button size="small" type="primary" onClick={() => setCommandeAPreparer(c)}>
                    {t('restaurateur.commandes.marquerPrete')}
                  </Button>
                )}
              </Space>
            ),
          },
        ]}
      />

      <ModaleBonDePreparation
        commande={commandeAPreparer}
        onFermer={() => setCommandeAPreparer(null)}
        onPrete={() => {
          setCommandeAPreparer(null)
          void invalider()
        }}
      />
    </div>
  )
}

function ModaleBonDePreparation({ commande, onFermer, onPrete }: { commande: Commande | null; onFermer: () => void; onPrete: () => void }) {
  const { t } = useTranslation()
  const [quantites, setQuantites] = useState<Record<number, number>>({})
  const [erreur, setErreur] = useState<string | null>(null)

  const marquerPrete = useMutation({
    mutationFn: () => {
      const parDefaut = Object.fromEntries((commande?.lignes ?? []).map((l) => [l.id, quantites[l.id] ?? l.quantite_commandee]))
      return marquerLaCommandePrete(commande!.id, parDefaut)
    },
    onSuccess: () => {
      setQuantites({})
      setErreur(null)
      onPrete()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('restaurateur.commandes.bonDePreparation', { reference: commande?.reference ?? '' })}
      open={commande !== null}
      onCancel={() => {
        setQuantites({})
        setErreur(null)
        onFermer()
      }}
      onOk={() => marquerPrete.mutate()}
      confirmLoading={marquerPrete.isPending}
      okText={t('restaurateur.commandes.marquerPrete')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
    >
      <Typography.Paragraph type="secondary">{t('restaurateur.commandes.bonDePreparationAide')}</Typography.Paragraph>
      <Space orientation="vertical" style={{ width: '100%' }}>
        {commande?.lignes.map((ligne) => (
          <Space key={ligne.id} style={{ width: '100%', justifyContent: 'space-between' }}>
            <Typography.Text>
              {ligne.nom_produit} <Typography.Text type="secondary">({t('restaurateur.commandes.quantiteCommandee', { quantite: ligne.quantite_commandee })})</Typography.Text>
            </Typography.Text>
            <InputNumber
              min={0}
              precision={0}
              value={quantites[ligne.id] ?? ligne.quantite_commandee}
              onChange={(v) => setQuantites((q) => ({ ...q, [ligne.id]: v ?? 0 }))}
              aria-label={t('restaurateur.commandes.quantiteServie', { produit: ligne.nom_produit })}
            />
          </Space>
        ))}
      </Space>
      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
