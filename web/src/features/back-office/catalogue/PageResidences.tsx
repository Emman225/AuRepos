import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Input, Select, Space, Table } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { FormulaireResidence } from './FormulaireResidence'
import { listerLesResidences } from './api'
import type { Residence } from './types'

/** Back office › Catalogue › Résidences (CdC § 7.1). */
export function PageResidences() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [disponibilite, setDisponibilite] = useState<'disponible' | 'occupee' | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)

  const filtres = { recherche: recherche || undefined, disponibilite, page, par_page: parPage }
  const residences = useQuery({ queryKey: ['backoffice', 'residences', filtres], queryFn: () => listerLesResidences(filtres) })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.catalogue')}
        actions={
          <Space>
            <BoutonsExport
              url="/backoffice/residences/export"
              filtres={{ recherche: recherche || undefined, disponibilite }}
              nomFichier="Residences"
            />
            <Link to="referentiels">
              <Button>{t('backoffice.catalogue.referentiels.titre')}</Button>
            </Link>
            <Button type="primary" onClick={() => setFormulaireOuvert(true)}>
              {t('backoffice.catalogue.residence.creer')}
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
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.catalogue.residence.disponibilite')}
          value={disponibilite}
          onChange={(v) => {
            setDisponibilite(v)
            reinitialiser()
          }}
          options={[
            { value: 'disponible', label: t('backoffice.catalogue.residence.disponible') },
            { value: 'occupee', label: t('backoffice.catalogue.residence.occupee') },
          ]}
        />
      </Space>

      <Table<Residence>
        rowKey="id"
        loading={residences.isPending}
        dataSource={residences.data?.elements}
        pagination={propsPagination(residences.data?.pagination.total)}
        columns={[
          {
            title: t('backoffice.catalogue.residence.nom'),
            dataIndex: 'nom',
            render: (_, r) => <Link to={`${r.id}`}>{r.nom}</Link>,
          },
          { title: t('backoffice.catalogue.residence.proprietaire'), dataIndex: ['proprietaire', 'nom'] },
          { title: t('backoffice.catalogue.residence.lieu'), dataIndex: ['lieu', 'libelle'] },
          {
            title: t('backoffice.catalogue.residence.logements'),
            dataIndex: 'nombre_logements',
            render: (v: number | undefined) => v ?? 0,
          },
          {
            title: t('backoffice.catalogue.residence.disponibilite'),
            dataIndex: 'disponibilite',
            render: (v: string) => (
              <StatutBadge domaine="disponibiliteResidence" code={v} libelle={t(`backoffice.catalogue.residence.${v}`)} />
            ),
          },
          {
            title: t('backoffice.catalogue.residence.active'),
            dataIndex: 'active',
            render: (v: boolean) => (
              <StatutBadge
                domaine="actif"
                code={v ? 'actif' : 'inactif'}
                libelle={v ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')}
              />
            ),
          },
        ]}
      />

      <FormulaireResidence
        ouvert={formulaireOuvert}
        onFermer={() => setFormulaireOuvert(false)}
        onEnregistree={() => {
          setFormulaireOuvert(false)
          void queryClient.invalidateQueries({ queryKey: ['backoffice', 'residences'] })
        }}
      />
    </div>
  )
}
