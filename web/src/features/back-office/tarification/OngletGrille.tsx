import { useQuery, useQueryClient, useMutation } from '@tanstack/react-query'
import { Alert, Button, Card, DatePicker, InputNumber, Select, Skeleton, Space, Table, Typography } from 'antd'
import dayjs from 'dayjs'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { ErreurApi, lire } from '../../../shared/api/client'
import { formaterPrix } from '../../../shared/format/devise'
import { afficherLaGrille, enregistrerLaGrille, simulerUnSejour, verifierLaGrille, type Cible } from './api'
import type { LigneDeSaison, LigneSaisie } from './types'

interface TypeLogementChoix {
  id: number
  nom: string
}

/** Grille tarifaire, vérification et simulation (CdC § 7.3). */
export function OngletGrille() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const queryClient = useQueryClient()
  const logementIdParam = searchParams.get('logement_id')

  const [typeChoisi, setTypeChoisi] = useState<number | undefined>(undefined)
  const cible: Cible | null = logementIdParam ? { logement_id: Number(logementIdParam) } : typeChoisi ? { type_logement_id: typeChoisi } : null

  const types = useQuery({ queryKey: ['referentiels', 'types-logement'], queryFn: () => lire<TypeLogementChoix[]>('/referentiels/types-logement') })
  const grille = useQuery({
    queryKey: ['backoffice', 'tarification', 'grille', cible],
    queryFn: () => afficherLaGrille(cible!),
    enabled: cible !== null,
  })
  const verification = useQuery({ queryKey: ['backoffice', 'tarification', 'verification'], queryFn: verifierLaGrille })

  const [saisie, setSaisie] = useState<Record<string, number | null>>({})
  useEffect(() => {
    if (!grille.data) return
    const initiale: Record<string, number | null> = {}
    for (const s of grille.data.saisons) {
      for (const t of grille.data.tranches) {
        initiale[`${s.id}-${t.id}`] = s.tarifs[t.id] ?? null
      }
    }
    setSaisie(initiale)
  }, [grille.data])

  const [erreur, setErreur] = useState<string | null>(null)
  const enregistrer = useMutation({
    mutationFn: () => {
      const lignes: LigneSaisie[] = []
      for (const cle of Object.keys(saisie)) {
        const [saisonId, trancheId] = cle.split('-').map(Number)
        lignes.push({ saison_id: saisonId, tranche_duree_id: trancheId, tarif: saisie[cle] })
      }
      return enregistrerLaGrille(cible!, lignes)
    },
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['backoffice', 'tarification', 'grille'] })
      void queryClient.invalidateQueries({ queryKey: ['backoffice', 'tarification', 'verification'] })
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  // Simulation
  const [logementSimulation, setLogementSimulation] = useState('')
  const [datesSimulation, setDatesSimulation] = useState<[dayjs.Dayjs, dayjs.Dayjs] | null>(null)
  const simulation = useMutation({
    mutationFn: () => simulerUnSejour(Number(logementSimulation), datesSimulation![0].format('YYYY-MM-DD'), datesSimulation![1].format('YYYY-MM-DD')),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      {verification.data && !verification.data.muette && (
        <Alert
          style={{ marginBottom: 16 }}
          type={verification.data.nombre_bloquantes > 0 ? 'error' : 'warning'}
          showIcon
          title={t('backoffice.tarification.grille.anomalies', { nombre: verification.data.anomalies.length })}
          description={
            <ul style={{ margin: 0, paddingInlineStart: 20 }}>
              {verification.data.anomalies.map((a, i) => (
                <li key={i}>
                  [{a.niveau === 'bloquant' ? t('backoffice.tarification.grille.bloquant') : t('backoffice.tarification.grille.information')}] {a.message}
                </li>
              ))}
            </ul>
          }
        />
      )}

      <Card title={t('backoffice.tarification.grille.titre')} style={{ marginBottom: 16 }}>
        {!logementIdParam && (
          <Space style={{ marginBottom: 16 }}>
            <Typography.Text>{t('backoffice.catalogue.logement.type')}</Typography.Text>
            <Select
              style={{ width: 260 }}
              value={typeChoisi}
              onChange={setTypeChoisi}
              loading={types.isPending}
              options={types.data?.map((v) => ({ value: v.id, label: v.nom }))}
              placeholder={t('backoffice.tarification.grille.choisirUnType')}
            />
          </Space>
        )}
        {logementIdParam && <Typography.Paragraph type="secondary">{t('backoffice.tarification.grille.pourCeLogement')}</Typography.Paragraph>}

        {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} />}

        {cible === null ? (
          <Typography.Text type="secondary">{t('backoffice.tarification.grille.choisirUneCible')}</Typography.Text>
        ) : grille.isPending ? (
          <Skeleton active paragraph={{ rows: 4 }} />
        ) : !grille.data ? null : (
          <>
            <Table<LigneDeSaison>
              rowKey="id"
              size="small"
              dataSource={grille.data.saisons}
              pagination={false}
              columns={[
                { title: t('backoffice.tarification.grille.saison'), dataIndex: 'nom' },
                {
                  title: t('backoffice.tarification.grille.periode'),
                  key: 'periode',
                  render: (_, s) => `${s.date_debut} — ${s.date_fin}`,
                },
                ...grille.data.tranches.map((tr) => ({
                  title: tr.nom,
                  key: `tranche-${tr.id}`,
                  render: (_: unknown, s: LigneDeSaison) => (
                    <InputNumber
                      style={{ width: 110 }}
                      min={0}
                      step={1000}
                      value={saisie[`${s.id}-${tr.id}`] ?? null}
                      onChange={(v) => setSaisie((prev) => ({ ...prev, [`${s.id}-${tr.id}`]: v }))}
                    />
                  ),
                })),
              ]}
            />
            <Button style={{ marginTop: 16 }} type="primary" loading={enregistrer.isPending} onClick={() => enregistrer.mutate()}>
              {t('backoffice.reservations.manuelle.enregistrer')}
            </Button>
          </>
        )}
      </Card>

      <Card title={t('backoffice.tarification.simulation.titre')}>
        <Space wrap>
          <Typography.Text>{t('backoffice.tarification.simulation.logementId')}</Typography.Text>
          <InputNumber value={logementSimulation ? Number(logementSimulation) : undefined} onChange={(v) => setLogementSimulation(v ? String(v) : '')} />
          <DatePicker.RangePicker
            format="DD/MM/YYYY"
            placeholder={[t('tunnel.arrivee'), t('tunnel.depart')]}
            value={datesSimulation}
            onChange={(v) => setDatesSimulation(v && v[0] && v[1] ? [v[0], v[1]] : null)}
          />
          <Button loading={simulation.isPending} disabled={!logementSimulation || !datesSimulation} onClick={() => simulation.mutate()}>
            {t('backoffice.tarification.simulation.simuler')}
          </Button>
        </Space>

        {simulation.data && (
          <div style={{ marginTop: 16 }}>
            <Typography.Paragraph>
              {t('backoffice.tarification.simulation.nombreDeNuits')} : {simulation.data.nombre_de_nuits} — {t('backoffice.tarification.simulation.hebergement')} :{' '}
              {formaterPrix(simulation.data.hebergement_hors_taxes)}
              {simulation.data.marge_hebergement !== null && ` — ${t('backoffice.catalogue.prix.marge')} : ${formaterPrix(simulation.data.marge_hebergement)}`}
            </Typography.Paragraph>
            <Table
              size="small"
              rowKey="date"
              pagination={false}
              dataSource={simulation.data.nuitees}
              columns={[
                { title: t('backoffice.tarification.simulation.date'), dataIndex: 'date' },
                { title: t('backoffice.tarification.grille.saison'), dataIndex: 'saison', render: (v: string | null) => v ?? '—' },
                { title: t('backoffice.tarification.simulation.tarif'), dataIndex: 'tarif', render: (v: number) => formaterPrix(v) },
                { title: t('backoffice.tarification.simulation.origine'), dataIndex: 'origine' },
              ]}
            />
          </div>
        )}
      </Card>
    </div>
  )
}
