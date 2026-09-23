import { useQuery } from '@tanstack/react-query'
import { Button, Space, Table, Tabs, Tag, Typography } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterDate } from '../../../shared/format/date'
import { formaterPrix } from '../../../shared/format/devise'
import { listerLesDevis, listerLesReservations, type FiltresReservations } from './api'
import { FormulaireReservationManuelle } from './FormulaireReservationManuelle'
import type { DevisBackOffice, ReservationBackOffice } from './types'

type Onglet = 'en_attente' | 'a_terme' | 'arrivees' | 'departs' | 'en_cours' | 'termines' | 'devis'

const ONGLETS: Onglet[] = ['en_attente', 'a_terme', 'arrivees', 'departs', 'en_cours', 'termines', 'devis']

const AUJOURD_HUI = dayjs().format('YYYY-MM-DD')

const FILTRES_PAR_ONGLET: Record<Exclude<Onglet, 'devis'>, FiltresReservations> = {
  en_attente: { etat: 'demande' },
  a_terme: { mode_reglement: 'a_terme' },
  arrivees: { etat: 'confirme', champ_date: 'arrivee', du: AUJOURD_HUI, au: AUJOURD_HUI },
  departs: { etat: 'arrive', champ_date: 'depart', du: AUJOURD_HUI, au: AUJOURD_HUI },
  en_cours: { etat: 'arrive' },
  termines: { etat: 'cloture' },
}

/** Back office › Réservations (CdC § 6.1) : les files de l'exploitation quotidienne. */
export function PageReservations() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const ongletInitial = searchParams.get('onglet')
  const [onglet, setOnglet] = useState<Onglet>(
    ongletInitial && (ONGLETS as string[]).includes(ongletInitial) ? (ongletInitial as Onglet) : 'en_attente',
  )
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()

  const reservations = useQuery({
    queryKey: ['backoffice', 'reservations', onglet, page],
    queryFn: () => listerLesReservations({ ...FILTRES_PAR_ONGLET[onglet as Exclude<Onglet, 'devis'>], page, par_page: parPage }),
    enabled: onglet !== 'devis',
  })
  const devis = useQuery({
    queryKey: ['backoffice', 'devis-en-attente', page],
    queryFn: () => listerLesDevis({ etat: 'en_attente', page, par_page: parPage }),
    enabled: onglet === 'devis',
  })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Typography.Title level={3} style={{ margin: 0 }}>
          {t('backoffice.menu.reservations')}
        </Typography.Title>
        <Space>
          {onglet === 'devis' ? (
            <BoutonsExport url="/backoffice/devis/export" filtres={{ etat: 'en_attente' }} nomFichier="Devis" />
          ) : (
            <BoutonsExport url="/backoffice/sejours/export" filtres={{ ...FILTRES_PAR_ONGLET[onglet] }} nomFichier="Reservations" />
          )}
          <Button type="primary" onClick={() => setFormulaireOuvert(true)}>
            {t('backoffice.reservations.manuelle.titre')}
          </Button>
        </Space>
      </div>

      <Tabs
        activeKey={onglet}
        onChange={(cle) => {
          setOnglet(cle as Onglet)
          reinitialiser()
        }}
        items={ONGLETS.map((cle) => ({ key: cle, label: t(`backoffice.reservations.onglets.${cle}`) }))}
      />

      {onglet === 'devis' ? (
        <Table<DevisBackOffice>
          rowKey="id"
          loading={devis.isPending}
          dataSource={devis.data?.elements}
          pagination={propsPagination(devis.data?.pagination.total)}
          columns={[
            { title: t('client.sejours.reference'), dataIndex: 'reference' },
            { title: t('backoffice.reservations.client'), dataIndex: ['client', 'nom'] },
            { title: t('client.sejours.logement'), dataIndex: ['logement', 'nom'] },
            { title: t('client.sejours.arrivee'), dataIndex: 'arrivee', render: formaterDate },
            { title: t('client.sejours.depart'), dataIndex: 'depart', render: formaterDate },
            { title: t('client.sejours.netAPayer'), dataIndex: 'net_a_payer', render: (v: number) => formaterPrix(v) },
          ]}
        />
      ) : (
        <Table<ReservationBackOffice>
          rowKey="id"
          loading={reservations.isPending}
          dataSource={reservations.data?.elements}
          pagination={propsPagination(reservations.data?.pagination.total)}
          columns={[
            {
              title: t('client.sejours.reference'),
              dataIndex: 'reference',
              render: (_, r) => <Link to={`${r.id}`}>{r.reference}</Link>,
            },
            { title: t('backoffice.reservations.client'), dataIndex: ['client', 'nom'] },
            { title: t('client.sejours.logement'), dataIndex: ['logement', 'nom'] },
            { title: t('client.sejours.arrivee'), dataIndex: 'arrivee', render: formaterDate },
            { title: t('client.sejours.depart'), dataIndex: 'depart', render: formaterDate },
            { title: t('client.sejours.etat'), dataIndex: 'etat_libelle', render: (v: string) => <Tag>{v}</Tag> },
            { title: t('client.sejours.netAPayer'), dataIndex: 'net_a_payer', render: (v: number) => formaterPrix(v) },
          ]}
        />
      )}

      <FormulaireReservationManuelle
        ouvert={formulaireOuvert}
        onFermer={() => setFormulaireOuvert(false)}
        onCree={() => {
          setFormulaireOuvert(false)
          setOnglet('en_attente')
          void reservations.refetch()
        }}
      />
    </div>
  )
}
