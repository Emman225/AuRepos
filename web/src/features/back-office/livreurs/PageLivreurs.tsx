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
import { gainsDuLivreur, listerLesLivreurs } from './api'
import { FormulaireLivreur } from './FormulaireLivreur'
import type { Livreur } from './types'

/** Back office › Livreurs de repas : CRUD simple, comme les apporteurs d'affaires. */
export function PageLivreurs() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [livreurEnEdition, setLivreurEnEdition] = useState<Livreur | null>(null)
  const [livreurConsulte, setLivreurConsulte] = useState<Livreur | null>(null)

  const filtres = { recherche: recherche || undefined, page, par_page: parPage }
  const livreurs = useQuery({ queryKey: ['backoffice', 'livreurs', filtres], queryFn: () => listerLesLivreurs(filtres) })

  const gains = useQuery({
    queryKey: ['backoffice', 'livreurs', livreurConsulte?.id, 'gains'],
    queryFn: () => gainsDuLivreur(livreurConsulte!.id),
    enabled: livreurConsulte !== null,
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'livreurs'] })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.livreurs')}
        actions={
          <Button type="primary" onClick={() => { setLivreurEnEdition(null); setFormulaireOuvert(true) }}>
            {t('backoffice.livreurs.creer')}
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

      <Table<Livreur>
        rowKey="id"
        loading={livreurs.isPending}
        dataSource={livreurs.data?.elements}
        pagination={propsPagination(livreurs.data?.pagination.total)}
        onRow={(l) => ({ onClick: () => setLivreurConsulte(l), style: { cursor: 'pointer' } })}
        locale={{ emptyText: <EtatVide titre={t('backoffice.livreurs.aucun')} /> }}
        columns={[
          { title: t('backoffice.proprietaires.nom'), dataIndex: ['compte', 'nom_complet'] },
          { title: t('backoffice.proprietaires.courriel'), dataIndex: ['compte', 'email'] },
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
            render: (_, l) => (
              <Button
                size="small"
                onClick={(e) => {
                  e.stopPropagation()
                  setLivreurEnEdition(l)
                  setFormulaireOuvert(true)
                }}
              >
                {t('backoffice.livreurs.modifier')}
              </Button>
            ),
          },
        ]}
      />

      <FormulaireLivreur
        ouvert={formulaireOuvert}
        livreur={livreurEnEdition}
        onFermer={() => setFormulaireOuvert(false)}
        onEnregistre={() => {
          setFormulaireOuvert(false)
          void invalider()
        }}
      />

      <Drawer title={livreurConsulte?.compte.nom_complet} open={livreurConsulte !== null} onClose={() => setLivreurConsulte(null)} width={380}>
        {gains.isPending ? (
          <Skeleton active paragraph={{ rows: 3 }} />
        ) : (
          gains.data && (
            <Space orientation="vertical" size={16} style={{ width: '100%' }}>
              <div>
                <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                  {t('backoffice.livreurs.soldeDu')}
                </Typography.Text>
                <Typography.Text strong style={{ fontSize: 24, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(gains.data.solde_du)}
                </Typography.Text>
              </div>
              <div>
                <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                  {t('backoffice.livreurs.totalGagne')}
                </Typography.Text>
                <Typography.Text strong style={{ fontSize: 18, fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(gains.data.total_gagne)}
                </Typography.Text>
              </div>
              <div>
                <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
                  {t('backoffice.livreurs.dejaVerse')}
                </Typography.Text>
                <Typography.Text strong style={{ fontSize: 18, fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(gains.data.deja_verse)}
                </Typography.Text>
              </div>
            </Space>
          )
        )}
      </Drawer>
    </div>
  )
}
