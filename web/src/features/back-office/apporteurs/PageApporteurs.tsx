import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Drawer, Input, Skeleton, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { couleurs } from '../../../shared/theme/jetons'
import { commissionsDeLApporteur, listerLesApporteurs } from './api'
import { FormulaireApporteur } from './FormulaireApporteur'
import type { Apporteur } from './types'

/** Back office › Apporteurs d'affaires : mandat de commission, filleuls, commissions dues (CdC — apporteurs). */
export function PageApporteurs() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [apporteurEnEdition, setApporteurEnEdition] = useState<Apporteur | null>(null)
  const [apporteurConsulte, setApporteurConsulte] = useState<Apporteur | null>(null)

  const filtres = { recherche: recherche || undefined, page, par_page: parPage }
  const apporteurs = useQuery({ queryKey: ['backoffice', 'apporteurs', filtres], queryFn: () => listerLesApporteurs(filtres) })

  const commissions = useQuery({
    queryKey: ['backoffice', 'apporteurs', apporteurConsulte?.id, 'commissions'],
    queryFn: () => commissionsDeLApporteur(apporteurConsulte!.id),
    enabled: apporteurConsulte !== null,
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'apporteurs'] })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.apporteurs')}
        actions={
          <Button type="primary" onClick={() => { setApporteurEnEdition(null); setFormulaireOuvert(true) }}>
            {t('backoffice.apporteurs.creer')}
          </Button>
        }
      />

      <Space wrap style={{ marginBottom: 16 }}>
        <Input.Search
          placeholder={t('backoffice.clients.recherche')}
          value={recherche}
          onChange={(e) => {
            setRecherche(e.target.value)
            reinitialiser()
          }}
          style={{ width: 260 }}
          allowClear
        />
      </Space>

      <Table<Apporteur>
        rowKey="id"
        loading={apporteurs.isPending}
        dataSource={apporteurs.data?.elements}
        pagination={propsPagination(apporteurs.data?.pagination.total)}
        onRow={(a) => ({ onClick: () => setApporteurConsulte(a), style: { cursor: 'pointer' } })}
        locale={{ emptyText: <EtatVide titre={t('backoffice.apporteurs.aucun')} /> }}
        columns={[
          { title: t('backoffice.apporteurs.code'), dataIndex: 'code' },
          { title: t('backoffice.proprietaires.nom'), dataIndex: ['compte', 'nom_complet'] },
          { title: t('backoffice.proprietaires.courriel'), dataIndex: ['compte', 'email'] },
          { title: t('backoffice.apporteurs.pourcentage'), dataIndex: 'pourcentage', render: (v: number) => `${v} %` },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: ['compte', 'statut'],
            render: (v: string) => <StatutBadge domaine="compte" code={v} libelle={t(`backoffice.clients.statuts.${v}`)} />,
          },
          {
            title: t('backoffice.apporteurs.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => (
              <StatutBadge domaine="actif" code={v ? 'actif' : 'inactif'} libelle={v ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')} />
            ),
          },
          {
            title: '',
            key: 'actions',
            render: (_, a) => (
              <Button
                size="small"
                onClick={(e) => {
                  e.stopPropagation()
                  setApporteurEnEdition(a)
                  setFormulaireOuvert(true)
                }}
              >
                {t('backoffice.apporteurs.modifier')}
              </Button>
            ),
          },
        ]}
      />

      <FormulaireApporteur
        ouvert={formulaireOuvert}
        apporteur={apporteurEnEdition}
        onFermer={() => setFormulaireOuvert(false)}
        onEnregistre={() => {
          setFormulaireOuvert(false)
          void invalider()
        }}
      />

      <Drawer title={apporteurConsulte?.compte.nom_complet} open={apporteurConsulte !== null} onClose={() => setApporteurConsulte(null)} width={420}>
        {commissions.isPending ? (
          <Skeleton active paragraph={{ rows: 4 }} />
        ) : (
          commissions.data && (
            <>
              <div style={{ marginBottom: 20 }}>
                <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                  {t('backoffice.apporteurs.soldeDu')}
                </Typography.Text>
                <Typography.Text strong style={{ fontSize: 24, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(commissions.data.solde_du)}
                </Typography.Text>
              </div>

              <Typography.Title level={5}>{t('backoffice.apporteurs.commissions')}</Typography.Title>
              {commissions.data.commissions.length === 0 ? (
                <EtatVide titre={t('backoffice.apporteurs.aucuneCommission')} />
              ) : (
                commissions.data.commissions.map((c) => (
                  <div key={c.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: `1px solid ${couleurs.bordure}` }}>
                    <div>
                      <Typography.Text strong style={{ display: 'block' }}>
                        {c.sejour_reference ?? '—'}
                      </Typography.Text>
                      <Typography.Text style={{ fontSize: 12, color: couleurs.texteDiscret }}>{c.cree_le}</Typography.Text>
                    </div>
                    <Typography.Text strong style={{ fontVariantNumeric: 'tabular-nums' }}>{formaterPrix(c.montant)}</Typography.Text>
                  </div>
                ))
              )}
            </>
          )
        )}
      </Drawer>
    </div>
  )
}
