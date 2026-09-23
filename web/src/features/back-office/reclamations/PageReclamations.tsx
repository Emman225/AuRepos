import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Select, Space, Table, Tooltip, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { listerLesParametres } from '../parametres/api'
import { fermerUneReclamation, listerLesChangementsEnAttente, listerLesReclamations } from './api'
import { FormulaireAvoir } from './FormulaireAvoir'
import { FormulaireDecisionChangement } from './FormulaireDecisionChangement'
import type { ChangementAValider, EtatDeLaReclamation, Reclamation } from './types'

const ETATS: EtatDeLaReclamation[] = ['ouverte', 'en_cours', 'fermee']

/** `gestionnaires.validant_2_id` (CdC § 6.4) : sans trésorier désigné, « Proposer un avoir » resterait un 422 côté API. */
function tresorierDesigne(onglets: { parametres: { cle: string; valeur: unknown }[] }[] | undefined): boolean {
  if (!onglets) return false
  for (const onglet of onglets) {
    const p = onglet.parametres.find((p2) => p2.cle === 'gestionnaires.validant_2_id')
    if (p) return p.valeur !== null && p.valeur !== undefined
  }
  return false
}

/** Back office › Réclamations (P2-AST-01, CdC § 6.4) : fermeture, avoir proposé puis confirmé par le trésorier désigné (double validation). */
export function PageReclamations() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<EtatDeLaReclamation | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [reclamationAFermer, setReclamationAFermer] = useState<Reclamation | null>(null)
  const [reponseFermeture, setReponseFermeture] = useState('')
  const [reclamationPourAvoir, setReclamationPourAvoir] = useState<Reclamation | null>(null)
  const [decision, setDecision] = useState<{ changement: ChangementAValider; type: 'valider' | 'refuser' } | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const filtres = { statut, page, par_page: parPage }
  const reclamations = useQuery({ queryKey: ['backoffice', 'reclamations', filtres], queryFn: () => listerLesReclamations(filtres) })
  const changements = useQuery({ queryKey: ['backoffice', 'changements', 'en-attente'], queryFn: listerLesChangementsEnAttente })
  const parametres = useQuery({ queryKey: ['backoffice', 'parametres'], queryFn: listerLesParametres })

  const peutProposerUnAvoir = tresorierDesigne(parametres.data?.onglets)

  const invalider = () => {
    void queryClient.invalidateQueries({ queryKey: ['backoffice', 'reclamations'] })
    void queryClient.invalidateQueries({ queryKey: ['backoffice', 'changements'] })
  }

  const changementDe = (reclamationId: number): ChangementAValider | undefined =>
    changements.data?.elements.find((c) => c.sujet_type === 'reclamation' && c.sujet_id === reclamationId && c.statut === 'en_attente')

  const fermer = useMutation({
    mutationFn: () => fermerUneReclamation(reclamationAFermer!.id, reponseFermeture || undefined),
    onSuccess: () => {
      setReclamationAFermer(null)
      invalider()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.reclamations')} />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.reclamations.tousLesStatuts')}
          value={statut}
          onChange={(v) => {
            setStatut(v)
            reinitialiser()
          }}
          options={ETATS.map((e) => ({ value: e, label: t(`backoffice.reclamations.statuts.${e}`) }))}
        />
      </Space>

      <Table<Reclamation>
        rowKey="id"
        loading={reclamations.isPending}
        dataSource={reclamations.data?.elements}
        pagination={propsPagination(reclamations.data?.pagination.total)}
        locale={{ emptyText: <EtatVide titre={t('backoffice.reclamations.aucune')} /> }}
        columns={[
          { title: t('backoffice.ticketsAssistance.sejour'), render: (_, r) => r.sejour?.reference ?? '—' },
          { title: t('backoffice.reservations.client'), dataIndex: 'client' },
          { title: t('backoffice.reclamations.motif'), dataIndex: 'motif', ellipsis: true },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'statut',
            render: (_, r) => <StatutBadge domaine="reclamation" code={r.statut} libelle={r.statut_libelle} />,
          },
          {
            title: t('backoffice.reclamations.avoir.titre'),
            render: (_, r) => {
              const enAttente = changementDe(r.id)
              if (r.avoir_montant !== null) return formaterPrix(r.avoir_montant)
              if (enAttente) return <Typography.Text type="warning">{t('backoffice.reclamations.avoir.enAttente')}</Typography.Text>
              return '—'
            },
          },
          {
            title: '',
            key: 'actions',
            render: (_, r) => {
              const enAttente = changementDe(r.id)
              return (
                <Space wrap size={4}>
                  {r.statut !== 'fermee' && (
                    <Button size="small" onClick={() => setReclamationAFermer(r)}>
                      {t('backoffice.reclamations.fermer')}
                    </Button>
                  )}
                  {r.statut !== 'fermee' && !enAttente && r.avoir_montant === null && (
                    <Tooltip title={!peutProposerUnAvoir ? t('backoffice.reclamations.avoir.aucunTresorier') : undefined}>
                      <Button size="small" disabled={!peutProposerUnAvoir} onClick={() => setReclamationPourAvoir(r)}>
                        {t('backoffice.reclamations.avoir.proposer')}
                      </Button>
                    </Tooltip>
                  )}
                  {enAttente?.je_peux_valider && (
                    <Button size="small" type="primary" onClick={() => setDecision({ changement: enAttente, type: 'valider' })}>
                      {t('backoffice.reclamations.changements.valider')}
                    </Button>
                  )}
                  {enAttente?.je_peux_valider && (
                    <Button size="small" danger onClick={() => setDecision({ changement: enAttente, type: 'refuser' })}>
                      {t('backoffice.reclamations.changements.refuser')}
                    </Button>
                  )}
                </Space>
              )
            },
          },
        ]}
      />

      <Modal
        title={t('backoffice.reclamations.fermer')}
        open={reclamationAFermer !== null}
        onCancel={() => setReclamationAFermer(null)}
        onOk={() => fermer.mutate()}
        confirmLoading={fermer.isPending}
        okText={t('listes.confirmation.confirmer')}
        cancelText={t('listes.confirmation.annuler')}
        destroyOnHidden
      >
        <Typography.Text>{t('backoffice.reclamations.reponse')}</Typography.Text>
        <Input.TextArea
          style={{ marginTop: 4 }}
          rows={3}
          value={reponseFermeture}
          onChange={(e) => setReponseFermeture(e.target.value)}
          aria-label={t('backoffice.reclamations.reponse')}
        />
        {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
      </Modal>

      <FormulaireAvoir
        reclamation={reclamationPourAvoir}
        onFermer={() => setReclamationPourAvoir(null)}
        onPropose={() => {
          setReclamationPourAvoir(null)
          invalider()
        }}
      />

      <FormulaireDecisionChangement
        changement={decision?.changement ?? null}
        decision={decision?.type ?? null}
        onFermer={() => setDecision(null)}
        onDecide={() => {
          setDecision(null)
          invalider()
        }}
      />
    </div>
  )
}
