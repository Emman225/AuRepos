import { useMutation } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { decaisser } from './api'
import type { ModeDeReglement } from './types'

const MODES: Exclude<ModeDeReglement, 'carte' | 'avance' | 'canal_externe'>[] = ['especes', 'mobile_money', 'virement', 'cheque']

/** Back office › Caisse › Décaissement (sortie de fonds, ex. reversement propriétaire ; réservé administrateur, CdC § 8.1). */
export function OngletDecaissement() {
  const { t } = useTranslation()
  const [beneficiaireId, setBeneficiaireId] = useState<number | null>(null)
  const [montant, setMontant] = useState<number | null>(null)
  const [mode, setMode] = useState<ModeDeReglement>('especes')
  const [referenceDuMode, setReferenceDuMode] = useState('')
  const [notes, setNotes] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const decaissementMutation = useMutation({
    mutationFn: () =>
      decaisser({ beneficiaire_id: beneficiaireId!, montant: montant!, mode, reference_du_mode: referenceDuMode || undefined, notes }),
    onSuccess: (reglement) => {
      setMessage(t('backoffice.caisse.decaissement.saisiMessage', { reference: reglement.reference }))
      setErreur(null)
      setMontant(null)
      setReferenceDuMode('')
      setNotes('')
    },
    onError: (e) => {
      setMessage(null)
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    },
  })

  return (
    <Space orientation="vertical" style={{ width: '100%', maxWidth: 480 }}>
      <Typography.Paragraph type="secondary">{t('backoffice.caisse.decaissement.aide')}</Typography.Paragraph>
      <Typography.Text>{t('backoffice.caisse.decaissement.beneficiaire')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} value={beneficiaireId} onChange={setBeneficiaireId} />
      <Typography.Text>{t('backoffice.caisse.encaissement.montant')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} step={1000} value={montant} onChange={setMontant} />
      <Typography.Text>{t('backoffice.caisse.encaissement.mode')}</Typography.Text>
      <Select style={{ width: '100%' }} value={mode} onChange={setMode} options={MODES.map((m) => ({ value: m, label: t(`backoffice.caisse.modes.${m}`) }))} />
      <Typography.Text>{t('backoffice.caisse.encaissement.referenceDuMode')}</Typography.Text>
      <Input value={referenceDuMode} onChange={(e) => setReferenceDuMode(e.target.value)} />
      <Typography.Text>{t('backoffice.caisse.encaissement.notes')}</Typography.Text>
      <Input.TextArea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} aria-label={t('backoffice.caisse.encaissement.notes')} />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      {message && <Alert type="success" showIcon title={message} />}
      <Button
        type="primary"
        danger
        loading={decaissementMutation.isPending}
        disabled={!beneficiaireId || !montant || notes.trim().length < 3}
        onClick={() => decaissementMutation.mutate()}
      >
        {t('backoffice.caisse.decaissement.decaisser')}
      </Button>
    </Space>
  )
}
