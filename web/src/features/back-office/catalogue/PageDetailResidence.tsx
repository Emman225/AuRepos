import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Skeleton, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { formaterPrix } from '../../../shared/format/devise'
import { FormulaireLogement } from './FormulaireLogement'
import { FormulaireResidence } from './FormulaireResidence'
import { afficherLaResidence } from './api'
import type { Logement } from './types'

/** Back office › Catalogue › Détail d'une résidence (CdC § 7.1) : ses logements. */
export function PageDetailResidence() {
  const { t } = useTranslation()
  const { id } = useParams<{ id: string }>()
  const idNombre = Number(id)
  const queryClient = useQueryClient()
  const [editionOuverte, setEditionOuverte] = useState(false)
  const [logementOuvert, setLogementOuvert] = useState(false)

  const residence = useQuery({
    queryKey: ['backoffice', 'residences', idNombre],
    queryFn: () => afficherLaResidence(idNombre),
    enabled: Number.isFinite(idNombre),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'residences', idNombre] })

  if (residence.isPending) return <Skeleton active paragraph={{ rows: 8 }} />
  if (!residence.data) return <Alert type="error" showIcon title={t('client.sejours.introuvable')} />

  const r = residence.data

  return (
    <div>
      <Link to="..">{t('client.detail.retourALaListe')}</Link>
      <EnTeteDePage titre={r.nom} actions={<Button onClick={() => setEditionOuverte(true)}>{t('backoffice.catalogue.residence.modifier')}</Button>} />
      <Typography.Paragraph>
        {r.lieu.libelle} — {t('backoffice.catalogue.residence.proprietaire')} : {r.proprietaire.nom}
      </Typography.Paragraph>
      {r.description && <Typography.Paragraph type="secondary">{r.description}</Typography.Paragraph>}
      <Space>
        <StatutBadge domaine="disponibiliteResidence" code={r.disponibilite} libelle={t(`backoffice.catalogue.residence.${r.disponibilite}`)} />
        <StatutBadge
          domaine="actif"
          code={r.active ? 'actif' : 'inactif'}
          libelle={r.active ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')}
        />
      </Space>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 32 }}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          {t('backoffice.catalogue.logement.titrePluriel')}
        </Typography.Title>
        <Button type="primary" onClick={() => setLogementOuvert(true)}>
          {t('backoffice.catalogue.logement.creer')}
        </Button>
      </div>

      <Table<Logement>
        rowKey="id"
        dataSource={r.logements}
        pagination={false}
        columns={[
          { title: t('backoffice.catalogue.logement.reference'), dataIndex: 'reference', render: (_, l) => <Link to={`logements/${l.id}`}>{l.reference}</Link> },
          { title: t('backoffice.catalogue.logement.nom'), dataIndex: 'nom' },
          { title: t('backoffice.catalogue.logement.type'), dataIndex: ['type', 'nom'] },
          { title: t('backoffice.catalogue.logement.capaciteMaximale'), dataIndex: 'capacite_maximale' },
          { title: t('backoffice.catalogue.logement.prixVente'), dataIndex: 'prix_vente', render: (v: number | null) => (v ? formaterPrix(v) : '—') },
          {
            title: t('backoffice.catalogue.logement.etatPublication'),
            dataIndex: 'etat_publication_libelle',
            render: (v: string, l) => <StatutBadge domaine="publicationLogement" code={l.etat_publication} libelle={v} />,
          },
        ]}
      />

      <FormulaireResidence
        ouvert={editionOuverte}
        residence={r}
        onFermer={() => setEditionOuverte(false)}
        onEnregistree={() => {
          setEditionOuverte(false)
          void invalider()
        }}
      />
      <FormulaireLogement
        ouvert={logementOuvert}
        residenceId={idNombre}
        onFermer={() => setLogementOuvert(false)}
        onEnregistre={() => {
          setLogementOuvert(false)
          void invalider()
        }}
      />
    </div>
  )
}
