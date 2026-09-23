import { useQuery } from '@tanstack/react-query'
import { Table } from 'antd'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { mesResidences } from './api'
import type { ResidenceProprietaire } from './types'

/** Espace propriétaire › Mes résidences (lecture seule). */
export function PageResidences() {
  const { t } = useTranslation()
  const naviguer = useNavigate()
  const residences = useQuery({ queryKey: ['proprietaire', 'residences'], queryFn: mesResidences })

  return (
    <div>
      <EnTeteDePage titre={t('proprietaire.menu.residences')} />

      <Table<ResidenceProprietaire>
        rowKey="id"
        loading={residences.isPending}
        dataSource={residences.data}
        pagination={false}
        onRow={(r) => ({ onClick: () => naviguer(`${r.id}`), style: { cursor: 'pointer' } })}
        locale={{ emptyText: <EtatVide titre={t('proprietaire.residences.aucune')} /> }}
        columns={[
          { title: t('backoffice.catalogue.residence.nom'), dataIndex: 'nom' },
          { title: t('backoffice.catalogue.residence.lieu'), key: 'lieu', render: (_, r) => r.lieu.libelle },
          { title: t('backoffice.catalogue.residence.logements'), dataIndex: 'nombre_logements' },
          {
            title: t('backoffice.catalogue.residence.disponibilite'),
            key: 'disponibilite',
            render: (_, r) => <StatutBadge domaine="disponibiliteResidence" code={r.disponibilite} libelle={r.disponibilite_libelle} />,
          },
          {
            title: t('backoffice.catalogue.residence.active'),
            key: 'active',
            render: (_, r) => (
              <StatutBadge
                domaine="actif"
                code={r.active ? 'actif' : 'inactif'}
                libelle={r.active ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')}
              />
            ),
          },
        ]}
      />
    </div>
  )
}
