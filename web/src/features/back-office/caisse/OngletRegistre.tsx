import { useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, DatePicker, InputNumber, Select, Space, Table } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { listerLesReglements, ouvrirLeRecu, renvoyerLeRecu } from './api'
import type { EtatDuReglement, Guichet, Reglement, Sens } from './types'

/** Back office › Caisse › Registre des règlements, avec export (CdC § 8.1). */
export function OngletRegistre() {
  const { t } = useTranslation()
  const [sens, setSens] = useState<Sens | undefined>(undefined)
  const [etat, setEtat] = useState<EtatDuReglement | undefined>(undefined)
  const [guichet, setGuichet] = useState<Guichet | undefined>(undefined)
  const [tiersId, setTiersId] = useState<number | null>(null)
  const [periode, setPeriode] = useState<[dayjs.Dayjs, dayjs.Dayjs] | null>(null)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [erreur, setErreur] = useState<string | null>(null)

  const filtres = {
    sens,
    etat,
    guichet,
    tiers_id: tiersId ?? undefined,
    du: periode ? periode[0].format('YYYY-MM-DD') : undefined,
    au: periode ? periode[1].format('YYYY-MM-DD') : undefined,
    page,
    par_page: parPage,
  }

  const reglements = useQuery({ queryKey: ['backoffice', 'caisse', 'reglements', 'registre', filtres], queryFn: () => listerLesReglements(filtres) })

  const ouvrir = useMutation({ mutationFn: (id: number) => ouvrirLeRecu(id), onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')) })
  const renvoyer = useMutation({ mutationFn: (id: number) => renvoyerLeRecu(id), onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')) })

  return (
    <div>
      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 160 }}
          allowClear
          placeholder={t('backoffice.caisse.registre.sens')}
          value={sens}
          onChange={(v) => {
            setSens(v)
            reinitialiser()
          }}
          options={[
            { value: 'encaissement', label: t('backoffice.caisse.sens.encaissement') },
            { value: 'decaissement', label: t('backoffice.caisse.sens.decaissement') },
          ]}
        />
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.caisse.registre.etat')}
          value={etat}
          onChange={(v) => {
            setEtat(v)
            reinitialiser()
          }}
          options={(['en_attente', 'a_payer', 'preuve_jointe', 'effectue', 'rejete'] as EtatDuReglement[]).map((e) => ({
            value: e,
            label: t(`backoffice.caisse.etats.${e}`),
          }))}
        />
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.caisse.registre.guichet')}
          value={guichet}
          onChange={(v) => {
            setGuichet(v)
            reinitialiser()
          }}
          options={(['sejours', 'creances_a_terme', 'en_ligne', 'avances', 'dettes_partenaires'] as Guichet[]).map((g) => ({
            value: g,
            label: t(`backoffice.caisse.guichets.${champGuichet(g)}`),
          }))}
        />
        <InputNumber
          placeholder={t('backoffice.caisse.registre.tiersId')}
          min={1}
          value={tiersId}
          onChange={(v) => {
            setTiersId(v)
            reinitialiser()
          }}
        />
        <DatePicker.RangePicker
          format="DD/MM/YYYY"
          value={periode}
          onChange={(v) => {
            setPeriode(v && v[0] && v[1] ? [v[0], v[1]] : null)
            reinitialiser()
          }}
        />
      </Space>

      <Space style={{ marginBottom: 16 }}>
        <BoutonsExport url="/backoffice/caisse/reglements/export" filtres={filtres} nomFichier="Reglements" />
      </Space>

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      <Table<Reglement>
        rowKey="id"
        size="small"
        loading={reglements.isPending}
        dataSource={reglements.data?.elements}
        pagination={propsPagination(reglements.data?.pagination.total)}
        columns={[
          { title: t('backoffice.caisse.registre.reference'), dataIndex: 'reference' },
          { title: t('backoffice.caisse.registre.sens'), dataIndex: 'sens', render: (v: Sens) => t(`backoffice.caisse.sens.${v}`) },
          { title: t('backoffice.caisse.registre.guichet'), dataIndex: 'guichet_libelle' },
          { title: t('backoffice.caisse.registre.tiers'), dataIndex: ['tiers', 'nom'] },
          { title: t('backoffice.caisse.registre.montant'), dataIndex: 'montant', render: (v: number) => formaterPrix(v) },
          { title: t('backoffice.caisse.registre.mode'), dataIndex: 'mode_libelle' },
          { title: t('backoffice.caisse.registre.etat'), dataIndex: 'etat', render: (v: EtatDuReglement, r) => <StatutBadge domaine="reglement" code={v} libelle={r.etat_libelle} /> },
          { title: t('backoffice.caisse.registre.numeroRecu'), dataIndex: 'numero_recu', render: (v: string | null) => v ?? '—' },
          {
            title: '',
            key: 'actions',
            render: (_, r) =>
              r.numero_recu ? (
                <Space>
                  <Button size="small" loading={ouvrir.isPending} onClick={() => ouvrir.mutate(r.id)}>
                    {t('backoffice.caisse.registre.voirLeRecu')}
                  </Button>
                  <Button size="small" loading={renvoyer.isPending} onClick={() => renvoyer.mutate(r.id)}>
                    {t('backoffice.caisse.registre.renvoyerLeRecu')}
                  </Button>
                </Space>
              ) : null,
          },
        ]}
      />
    </div>
  )
}

function champGuichet(g: Guichet): string {
  const cles: Record<Guichet, string> = {
    sejours: 'sejours',
    creances_a_terme: 'creancesATerme',
    en_ligne: 'enLigne',
    avances: 'avances',
    dettes_partenaires: 'dettesPartenaires',
  }
  return cles[g]
}
