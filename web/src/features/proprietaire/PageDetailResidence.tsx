import { useQuery } from '@tanstack/react-query'
import { Drawer, Skeleton, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { EtatVide } from '../../shared/composants/EtatVide'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { formaterDate } from '../../shared/format/date'
import { couleurs } from '../../shared/theme/jetons'
import { logementsDeLaResidence, mesResidences, sejoursDuLogement } from './api'
import type { LogementProprietaire } from './types'

/** Espace propriétaire › Logements d'une résidence, avec les séjours de chacun en tiroir (lecture seule). */
export function PageDetailResidence() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const residenceId = Number(id)
  const [logementOuvert, setLogementOuvert] = useState<LogementProprietaire | null>(null)

  // La liste des résidences porte déjà le nom/lieu ; pas d'appel dédié « une résidence » côté API pour ce lecture-seule minimal.
  const residences = useQuery({ queryKey: ['proprietaire', 'residences'], queryFn: mesResidences })
  const residence = residences.data?.find((r) => r.id === residenceId)

  const logements = useQuery({
    queryKey: ['proprietaire', 'residences', residenceId, 'logements'],
    queryFn: () => logementsDeLaResidence(residenceId),
    enabled: Number.isFinite(residenceId),
  })

  const sejours = useQuery({
    queryKey: ['proprietaire', 'logements', logementOuvert?.id, 'sejours'],
    queryFn: () => sejoursDuLogement(logementOuvert!.id),
    enabled: logementOuvert !== null,
  })

  return (
    <div>
      <Link to=".." style={{ fontSize: 13 }}>
        {t('client.detail.retourALaListe')}
      </Link>

      <Typography.Title level={3} style={{ marginTop: 8, marginBottom: 20 }}>
        {residence?.nom ?? '…'}
      </Typography.Title>

      <Table<LogementProprietaire>
        rowKey="id"
        loading={logements.isPending}
        dataSource={logements.data}
        pagination={false}
        onRow={(l) => ({ onClick: () => setLogementOuvert(l), style: { cursor: 'pointer' } })}
        locale={{ emptyText: <EtatVide titre={t('proprietaire.residence.aucunLogement')} /> }}
        columns={[
          { title: t('backoffice.catalogue.logement.reference'), dataIndex: 'reference' },
          { title: t('backoffice.catalogue.logement.nom'), dataIndex: 'nom' },
          { title: t('proprietaire.residence.capacite'), key: 'capacite', render: (_, l) => l.capacite_maximale },
          {
            title: t('backoffice.catalogue.logement.etatPublication'),
            key: 'etat_publication',
            render: (_, l) => <StatutBadge domaine="publicationLogement" code={l.etat_publication} libelle={l.etat_publication_libelle} />,
          },
        ]}
      />

      <Drawer
        title={logementOuvert?.nom}
        open={logementOuvert !== null}
        onClose={() => setLogementOuvert(null)}
        width={420}
      >
        <Typography.Title level={5} style={{ marginTop: 0 }}>
          {t('proprietaire.logement.sejours')}
        </Typography.Title>
        {sejours.isPending ? (
          <Skeleton active paragraph={{ rows: 4 }} />
        ) : !sejours.data || sejours.data.length === 0 ? (
          <EtatVide titre={t('proprietaire.logement.aucunSejour')} />
        ) : (
          sejours.data.map((s) => (
            <div key={s.reference} style={{ padding: '10px 0', borderBottom: `1px solid ${couleurs.bordure}` }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 4 }}>
                <Typography.Text strong>{s.reference}</Typography.Text>
                <StatutBadge domaine="sejour" code={s.etat} libelle={s.etat_libelle} />
              </div>
              <Typography.Text style={{ display: 'block', fontSize: 13, color: couleurs.texteDiscret }}>
                {formaterDate(s.arrivee)} → {formaterDate(s.depart)} ({t('client.detail.nuits', { count: s.nombre_de_nuits })})
              </Typography.Text>
              <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret }}>{s.client}</Typography.Text>
            </div>
          ))
        )}
      </Drawer>
    </div>
  )
}
