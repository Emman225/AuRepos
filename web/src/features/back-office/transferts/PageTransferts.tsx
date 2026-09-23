import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Select, Space, Table } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { useConfirmerAction } from '../../../shared/composants/confirmer'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { annulerLeTransfert, listerLesTransferts } from './api'
import { FormulaireAffectation } from './FormulaireAffectation'
import type { TransfertBackOffice } from './types'

const ETATS = ['demande', 'affecte', 'termine', 'annule'] as const

/** Back office › Transferts (CdC § 6.6) : file des transferts demandés, affectation chauffeur/véhicule. Le code de prise en charge n'est jamais lu ici (CdC § 11). */
export function PageTransferts() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [etat, setEtat] = useState<string | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [transfertAAffecter, setTransfertAAffecter] = useState<TransfertBackOffice | null>(null)

  const filtres = { etat, page, par_page: parPage }
  const transferts = useQuery({ queryKey: ['backoffice', 'transferts', filtres], queryFn: () => listerLesTransferts(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'transferts'] })

  const annuler = async (t2: TransfertBackOffice) => {
    const confirme = await confirmer({ titre: t('backoffice.transferts.confirmerAnnulation'), danger: true })
    if (!confirme) return
    await annulerLeTransfert(t2.id)
    void invalider()
  }

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.menu.transferts')} />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.transferts.tousLesEtats')}
          value={etat}
          onChange={(v) => {
            setEtat(v)
            reinitialiser()
          }}
          options={ETATS.map((e) => ({ value: e, label: t(`backoffice.transferts.etats.${e}`) }))}
        />
      </Space>

      <Table<TransfertBackOffice>
        rowKey="id"
        loading={transferts.isPending}
        dataSource={transferts.data?.elements}
        pagination={propsPagination(transferts.data?.pagination.total)}
        onRow={(t2) => ({
          onClick: () => {
            if (t2.etat === 'demande') setTransfertAAffecter(t2)
          },
          style: { cursor: t2.etat === 'demande' ? 'pointer' : 'default' },
        })}
        locale={{ emptyText: <EtatVide titre={t('backoffice.transferts.aucun')} /> }}
        columns={[
          { title: t('backoffice.transferts.reference'), dataIndex: 'reference' },
          { title: t('backoffice.transferts.sejour'), dataIndex: 'sejour_reference', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.transferts.lieu'), dataIndex: 'lieu_de_prise_en_charge' },
          { title: t('backoffice.transferts.dateHeure'), dataIndex: 'date_heure_prevue' },
          { title: t('backoffice.transferts.montant'), dataIndex: 'montant', render: (v: number) => formaterPrix(v) },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'etat',
            render: (_, r) => <StatutBadge domaine="transfert" code={r.etat} libelle={r.etat_libelle} />,
          },
          { title: t('backoffice.transferts.chauffeur'), dataIndex: 'chauffeur', render: (v: string | null) => v ?? '—' },
          {
            title: '',
            key: 'actions',
            render: (_, r) =>
              r.etat === 'demande' || r.etat === 'affecte' ? (
                <Button
                  size="small"
                  danger
                  onClick={(e) => {
                    e.stopPropagation()
                    void annuler(r)
                  }}
                >
                  {t('backoffice.transferts.annuler')}
                </Button>
              ) : null,
          },
        ]}
      />

      <FormulaireAffectation
        ouvert={transfertAAffecter !== null}
        transfert={transfertAAffecter}
        onFermer={() => setTransfertAAffecter(null)}
        onAffecte={() => {
          setTransfertAAffecter(null)
          void invalider()
        }}
      />
    </div>
  )
}
