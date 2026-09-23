import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Input, Space, Table } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { FormulaireProprietaire } from './FormulaireProprietaire'
import { listerLesProprietaires } from './api'
import type { Proprietaire } from './types'

/** Back office › Propriétaires (CdC § 7.2). */
export function PageProprietaires() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)

  const filtres = { recherche: recherche || undefined, page, par_page: parPage }
  const proprietaires = useQuery({ queryKey: ['backoffice', 'proprietaires', filtres], queryFn: () => listerLesProprietaires(filtres) })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.proprietaires')}
        actions={
          <Space>
            <BoutonsExport
              url="/backoffice/proprietaires/export"
              filtres={{ recherche: recherche || undefined }}
              nomFichier="Proprietaires"
            />
            <Button type="primary" onClick={() => setFormulaireOuvert(true)}>
              {t('backoffice.proprietaires.creer')}
            </Button>
          </Space>
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

      <Table<Proprietaire>
        rowKey="id"
        loading={proprietaires.isPending}
        dataSource={proprietaires.data?.elements}
        pagination={propsPagination(proprietaires.data?.pagination.total)}
        columns={[
          {
            title: t('backoffice.proprietaires.nom'),
            dataIndex: 'nom_affiche',
            render: (_, p) => <Link to={`${p.id}`}>{p.nom_affiche}</Link>,
          },
          { title: t('backoffice.proprietaires.nature'), dataIndex: 'nature_libelle' },
          { title: t('backoffice.proprietaires.courriel'), dataIndex: ['compte', 'email'] },
          { title: t('backoffice.proprietaires.residences'), dataIndex: 'nombre_residences', render: (v: number | undefined) => v ?? 0 },
          {
            title: t('backoffice.proprietaires.dossier'),
            dataIndex: 'dossier_complet',
            render: (v: boolean) => (
              <StatutBadge domaine="actif" code={v ? 'actif' : 'inactif'} libelle={v ? t('backoffice.proprietaires.complet') : t('backoffice.proprietaires.incomplet')} />
            ),
          },
        ]}
      />

      <FormulaireProprietaire
        ouvert={formulaireOuvert}
        onFermer={() => setFormulaireOuvert(false)}
        onEnregistre={() => {
          setFormulaireOuvert(false)
          void queryClient.invalidateQueries({ queryKey: ['backoffice', 'proprietaires'] })
        }}
      />
    </div>
  )
}
