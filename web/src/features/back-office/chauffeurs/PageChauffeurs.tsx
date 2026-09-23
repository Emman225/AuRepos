import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Drawer, Input, Skeleton, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { couleurs } from '../../../shared/theme/jetons'
import { listerLesChauffeurs, vehiculesDuChauffeur } from './api'
import { FormulaireChauffeur } from './FormulaireChauffeur'
import { FormulaireVehicule } from './FormulaireVehicule'
import type { Chauffeur } from './types'

/** Back office › Chauffeurs (CdC § 6.6) : CRUD compte + gestion de leurs véhicules, même patron que PageApporteurs. */
export function PageChauffeurs() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [chauffeurEnEdition, setChauffeurEnEdition] = useState<Chauffeur | null>(null)
  const [chauffeurConsulte, setChauffeurConsulte] = useState<Chauffeur | null>(null)

  const filtres = { recherche: recherche || undefined, page, par_page: parPage }
  const chauffeurs = useQuery({ queryKey: ['backoffice', 'chauffeurs', filtres], queryFn: () => listerLesChauffeurs(filtres) })

  const vehicules = useQuery({
    queryKey: ['backoffice', 'chauffeurs', chauffeurConsulte?.id, 'vehicules'],
    queryFn: () => vehiculesDuChauffeur(chauffeurConsulte!.id),
    enabled: chauffeurConsulte !== null,
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'chauffeurs'] })
  const invaliderVehicules = () =>
    queryClient.invalidateQueries({ queryKey: ['backoffice', 'chauffeurs', chauffeurConsulte?.id, 'vehicules'] })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.chauffeurs')}
        actions={
          <Button type="primary" onClick={() => { setChauffeurEnEdition(null); setFormulaireOuvert(true) }}>
            {t('backoffice.chauffeurs.creer')}
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

      <Table<Chauffeur>
        rowKey="id"
        loading={chauffeurs.isPending}
        dataSource={chauffeurs.data?.elements}
        pagination={propsPagination(chauffeurs.data?.pagination.total)}
        onRow={(c) => ({ onClick: () => setChauffeurConsulte(c), style: { cursor: 'pointer' } })}
        locale={{ emptyText: <EtatVide titre={t('backoffice.chauffeurs.aucun')} /> }}
        columns={[
          { title: t('backoffice.proprietaires.nom'), dataIndex: ['compte', 'nom_complet'] },
          { title: t('backoffice.proprietaires.courriel'), dataIndex: ['compte', 'email'] },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: ['compte', 'statut'],
            render: (v: string) => <StatutBadge domaine="compte" code={v} libelle={t(`backoffice.clients.statuts.${v}`)} />,
          },
          {
            title: t('backoffice.chauffeurs.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => (
              <StatutBadge domaine="actif" code={v ? 'actif' : 'inactif'} libelle={v ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')} />
            ),
          },
          {
            title: '',
            key: 'actions',
            render: (_, c) => (
              <Button
                size="small"
                onClick={(e) => {
                  e.stopPropagation()
                  setChauffeurEnEdition(c)
                  setFormulaireOuvert(true)
                }}
              >
                {t('backoffice.chauffeurs.modifier')}
              </Button>
            ),
          },
        ]}
      />

      <FormulaireChauffeur
        ouvert={formulaireOuvert}
        chauffeur={chauffeurEnEdition}
        onFermer={() => setFormulaireOuvert(false)}
        onEnregistre={() => {
          setFormulaireOuvert(false)
          void invalider()
        }}
      />

      <Drawer title={chauffeurConsulte?.compte.nom_complet} open={chauffeurConsulte !== null} onClose={() => setChauffeurConsulte(null)} width={420}>
        {chauffeurConsulte && (
          <>
            <Typography.Title level={5}>{t('backoffice.chauffeurs.vehicules')}</Typography.Title>
            {vehicules.isPending ? (
              <Skeleton active paragraph={{ rows: 3 }} />
            ) : vehicules.data && vehicules.data.length === 0 ? (
              <EtatVide titre={t('backoffice.chauffeurs.aucunVehicule')} />
            ) : (
              vehicules.data?.map((v) => (
                <div key={v.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderBottom: `1px solid ${couleurs.bordure}` }}>
                  <div>
                    <Typography.Text strong style={{ display: 'block' }}>
                      {v.immatriculation}
                    </Typography.Text>
                    <Typography.Text style={{ fontSize: 12, color: couleurs.texteDiscret }}>{v.type_vehicule ?? '—'}</Typography.Text>
                  </div>
                  <StatutBadge domaine="actif" code={v.actif ? 'actif' : 'inactif'} libelle={v.actif ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')} />
                </div>
              ))
            )}

            <div style={{ marginTop: 20 }}>
              <FormulaireVehicule chauffeurId={chauffeurConsulte.id} onAjoute={() => void invaliderVehicules()} />
            </div>
          </>
        )}
      </Drawer>
    </div>
  )
}
