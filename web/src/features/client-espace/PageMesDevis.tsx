import { useQuery } from '@tanstack/react-query'
import { Table, Tag, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formaterDate } from '../../shared/format/date'
import { formaterPrix } from '../../shared/format/devise'
import type { Devis } from '../reservation/types'
import { mesDevis } from './api'

const COULEUR_ETAT: Record<string, string> = { en_attente: 'gold', transforme: 'green', archive: 'default' }

/** Espace client › Mes devis (CdC § 5.1, 5.3) : prix figés, transformation en un clic. */
export function PageMesDevis() {
  const { t } = useTranslation()
  const devis = useQuery({ queryKey: ['client', 'devis'], queryFn: mesDevis })

  return (
    <div>
      <Typography.Title level={3}>{t('client.menu.devis')}</Typography.Title>
      <Table<Devis>
        rowKey="reference"
        loading={devis.isPending}
        dataSource={devis.data?.elements}
        pagination={false}
        columns={[
          {
            title: t('client.sejours.reference'),
            dataIndex: 'reference',
            render: (_, d) => <Link to={`${d.reference}`}>{d.reference}</Link>,
          },
          { title: t('client.sejours.logement'), dataIndex: ['logement', 'nom'] },
          { title: t('client.sejours.arrivee'), dataIndex: 'arrivee', render: formaterDate },
          { title: t('client.sejours.depart'), dataIndex: 'depart', render: formaterDate },
          {
            title: t('client.sejours.etat'),
            dataIndex: 'etat_libelle',
            render: (v: string, d) => <Tag color={COULEUR_ETAT[d.etat]}>{v}</Tag>,
          },
          {
            title: t('client.sejours.netAPayer'),
            dataIndex: 'net_a_payer',
            render: (v: number) => formaterPrix(v),
          },
        ]}
      />
    </div>
  )
}
