import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Switch, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { formaterPrix } from '../../../shared/format/devise'
import { afficherLesAffairesDuClient, encaisser } from './api'
import type { Affaire, Guichet, ModeDeReglement } from './types'

const MODES: ModeDeReglement[] = ['especes', 'mobile_money', 'carte', 'virement', 'cheque']

/** Back office › Caisse › Encaissement (guichets Séjours et Créances à terme, CdC § 8.1). */
export function OngletEncaissement() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [clientId, setClientId] = useState<number | null>(null)
  const [guichet, setGuichet] = useState<Extract<Guichet, 'sejours' | 'creances_a_terme'>>('sejours')
  const [clientCharge, setClientCharge] = useState<number | null>(null)
  const [affairesChoisies, setAffairesChoisies] = useState<number[]>([])
  const [montant, setMontant] = useState<number | null>(null)
  const [mode, setMode] = useState<ModeDeReglement>('especes')
  const [referenceDuMode, setReferenceDuMode] = useState('')
  const [notes, setNotes] = useState('')
  const [surplusEnAvance, setSurplusEnAvance] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const affaires = useQuery({
    queryKey: ['backoffice', 'caisse', 'affaires', clientCharge],
    queryFn: () => afficherLesAffairesDuClient(clientCharge!),
    enabled: clientCharge !== null,
  })

  const encaissementMutation = useMutation({
    mutationFn: () =>
      encaisser({
        client_id: clientCharge!,
        sejours: affairesChoisies,
        montant: montant!,
        mode,
        reference_du_mode: referenceDuMode || undefined,
        notes,
        guichet,
        surplus_en_avance: surplusEnAvance,
      }),
    onSuccess: (reglement) => {
      setMessage(t('backoffice.caisse.encaissement.saisiMessage', { reference: reglement.reference }))
      setErreur(null)
      setAffairesChoisies([])
      setMontant(null)
      setReferenceDuMode('')
      setNotes('')
      setSurplusEnAvance(false)
      void queryClient.invalidateQueries({ queryKey: ['backoffice', 'caisse', 'affaires', clientCharge] })
      void queryClient.invalidateQueries({ queryKey: ['backoffice', 'caisse', 'reglements'] })
    },
    onError: (e) => {
      setMessage(null)
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    },
  })

  return (
    <div>
      <Space wrap style={{ marginBottom: 16 }}>
        <Typography.Text>{t('backoffice.caisse.encaissement.client')}</Typography.Text>
        <InputNumber min={1} value={clientId} onChange={setClientId} />
        <Typography.Text>{t('backoffice.caisse.encaissement.guichet')}</Typography.Text>
        <Select
          style={{ width: 220 }}
          value={guichet}
          onChange={setGuichet}
          options={[
            { value: 'sejours', label: t('backoffice.caisse.guichets.sejours') },
            { value: 'creances_a_terme', label: t('backoffice.caisse.guichets.creancesATerme') },
          ]}
        />
        <Button
          onClick={() => {
            setClientCharge(clientId)
            setAffairesChoisies([])
          }}
          disabled={!clientId}
        >
          {t('backoffice.caisse.encaissement.chargerLesAffaires')}
        </Button>
      </Space>

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}
      {message && <Alert style={{ marginBottom: 16 }} type="success" showIcon title={message} closable onClose={() => setMessage(null)} />}

      {clientCharge !== null && (
        <>
          <Table<Affaire>
            rowKey="id"
            size="small"
            loading={affaires.isPending}
            dataSource={affaires.data?.affaires}
            pagination={false}
            style={{ marginBottom: 16 }}
            rowSelection={{
              selectedRowKeys: affairesChoisies,
              onChange: (cles) => setAffairesChoisies(cles as number[]),
            }}
            locale={{ emptyText: t('backoffice.caisse.encaissement.aucuneAffaire') }}
            columns={[
              { title: t('backoffice.caisse.encaissement.reference'), dataIndex: 'reference' },
              { title: t('backoffice.catalogue.logement.nom'), dataIndex: 'logement' },
              { title: t('backoffice.caisse.encaissement.periode'), key: 'periode', render: (_, a) => `${a.arrivee} — ${a.depart}` },
              { title: t('backoffice.reservations.detail.resteDu'), dataIndex: 'reste_du', render: (v: number) => formaterPrix(v) },
              { title: t('backoffice.caisse.encaissement.enCours'), dataIndex: 'en_cours', render: (v: number) => formaterPrix(v) },
            ]}
          />

          <Space wrap align="start">
            <Space orientation="vertical" size={4}>
              <Typography.Text>{t('backoffice.caisse.encaissement.montant')}</Typography.Text>
              <InputNumber min={1} step={1000} value={montant} onChange={setMontant} style={{ width: 200 }} />
            </Space>
            <Space orientation="vertical" size={4}>
              <Typography.Text>{t('backoffice.caisse.encaissement.mode')}</Typography.Text>
              <Select
                style={{ width: 180 }}
                value={mode}
                onChange={setMode}
                options={MODES.map((m) => ({ value: m, label: t(`backoffice.caisse.modes.${m}`) }))}
              />
            </Space>
            <Space orientation="vertical" size={4}>
              <Typography.Text>{t('backoffice.caisse.encaissement.referenceDuMode')}</Typography.Text>
              <Input value={referenceDuMode} onChange={(e) => setReferenceDuMode(e.target.value)} style={{ width: 200 }} />
            </Space>
          </Space>

          <div style={{ marginTop: 12 }}>
            <Typography.Text>{t('backoffice.caisse.encaissement.notes')}</Typography.Text>
            <Input.TextArea
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={2}
              style={{ maxWidth: 480 }}
              aria-label={t('backoffice.caisse.encaissement.notes')}
            />
          </div>

          <Space style={{ marginTop: 12 }}>
            <Switch checked={surplusEnAvance} onChange={setSurplusEnAvance} />
            <Typography.Text>{t('backoffice.caisse.encaissement.surplusEnAvance')}</Typography.Text>
          </Space>

          <div style={{ marginTop: 16 }}>
            <Button
              type="primary"
              loading={encaissementMutation.isPending}
              disabled={affairesChoisies.length === 0 || !montant || notes.trim().length < 3}
              onClick={() => encaissementMutation.mutate()}
            >
              {t('backoffice.caisse.encaissement.encaisser')}
            </Button>
          </div>
        </>
      )}
    </div>
  )
}
