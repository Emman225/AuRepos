import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Card, Input, InputNumber, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { formaterPrix } from '../../../shared/format/devise'
import { afficherLaSituationDesAvances, deposerUneAvance } from './api'
import type { DepotAvance, ModeDeReglement } from './types'

const MODES: ModeDeReglement[] = ['especes', 'mobile_money', 'carte', 'virement', 'cheque']

/** Back office › Caisse › Avances clients (CdC § 8.1). */
export function OngletAvances() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [clientId, setClientId] = useState<number | null>(null)
  const [clientConsulte, setClientConsulte] = useState<number | null>(null)
  const [depotOuvert, setDepotOuvert] = useState(false)

  const situation = useQuery({
    queryKey: ['backoffice', 'caisse', 'avances', clientConsulte],
    queryFn: () => afficherLaSituationDesAvances(clientConsulte!),
    enabled: clientConsulte !== null,
  })

  return (
    <div>
      <Space wrap style={{ marginBottom: 16 }}>
        <Typography.Text>{t('backoffice.caisse.encaissement.client')}</Typography.Text>
        <InputNumber min={1} value={clientId} onChange={setClientId} />
        <Button onClick={() => setClientConsulte(clientId)} disabled={!clientId}>
          {t('backoffice.caisse.avances.consulter')}
        </Button>
        <Button type="primary" onClick={() => setDepotOuvert(true)}>
          {t('backoffice.caisse.avances.nouveauDepot')}
        </Button>
      </Space>

      {clientConsulte !== null && situation.data && (
        <Card title={t('backoffice.caisse.avances.situationTitre', { client: situation.data.client.nom })} loading={situation.isPending}>
          <Typography.Paragraph>
            {t('backoffice.caisse.avances.disponible')} : <strong>{formaterPrix(situation.data.disponible)}</strong>
          </Typography.Paragraph>
          <Table<DepotAvance>
            rowKey="id"
            size="small"
            dataSource={situation.data.depots}
            pagination={false}
            locale={{ emptyText: t('backoffice.caisse.avances.aucunDepot') }}
            columns={[
              { title: t('backoffice.caisse.avances.date'), dataIndex: 'date' },
              { title: t('backoffice.caisse.avances.montant'), dataIndex: 'montant', render: (v: number) => formaterPrix(v) },
              { title: t('backoffice.caisse.avances.utilise'), dataIndex: 'utilise', render: (v: number) => formaterPrix(v) },
              { title: t('backoffice.caisse.avances.solde'), dataIndex: 'solde', render: (v: number) => formaterPrix(v) },
              { title: t('backoffice.caisse.registre.numeroRecu'), dataIndex: 'numero_recu', render: (v: string | null) => v ?? '—' },
            ]}
          />
        </Card>
      )}

      <Modal
        title={t('backoffice.caisse.avances.nouveauDepot')}
        open={depotOuvert}
        onCancel={() => setDepotOuvert(false)}
        footer={null}
        destroyOnHidden
      >
        <FormulaireDepotAvance
          clientIdPropose={clientId}
          onEnregistre={() => {
            setDepotOuvert(false)
            if (clientConsulte !== null) void queryClient.invalidateQueries({ queryKey: ['backoffice', 'caisse', 'avances', clientConsulte] })
          }}
        />
      </Modal>
    </div>
  )
}

function FormulaireDepotAvance({ clientIdPropose, onEnregistre }: { clientIdPropose: number | null; onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [clientId, setClientId] = useState<number | null>(clientIdPropose)
  const [montant, setMontant] = useState<number | null>(null)
  const [mode, setMode] = useState<ModeDeReglement>('especes')
  const [referenceDuMode, setReferenceDuMode] = useState('')
  const [notes, setNotes] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const deposer = useMutation({
    mutationFn: () =>
      deposerUneAvance({ client_id: clientId!, montant: montant!, mode, reference_du_mode: referenceDuMode || undefined, notes }),
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.caisse.encaissement.client')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} value={clientId} onChange={setClientId} />
      <Typography.Text>{t('backoffice.caisse.avances.montant')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} step={1000} value={montant} onChange={setMontant} />
      <Typography.Text>{t('backoffice.caisse.encaissement.mode')}</Typography.Text>
      <Select style={{ width: '100%' }} value={mode} onChange={setMode} options={MODES.map((m) => ({ value: m, label: t(`backoffice.caisse.modes.${m}`) }))} />
      <Typography.Text>{t('backoffice.caisse.encaissement.referenceDuMode')}</Typography.Text>
      <Input value={referenceDuMode} onChange={(e) => setReferenceDuMode(e.target.value)} />
      <Typography.Text>{t('backoffice.caisse.encaissement.notes')}</Typography.Text>
      <Input.TextArea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} aria-label={t('backoffice.caisse.encaissement.notes')} />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button
        type="primary"
        loading={deposer.isPending}
        disabled={!clientId || !montant || notes.trim().length < 3}
        onClick={() => deposer.mutate()}
      >
        {t('backoffice.caisse.avances.deposer')}
      </Button>
    </Space>
  )
}
