import { useMutation } from '@tanstack/react-query'
import { Alert, Form, Input, Select } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { accepterUneDemandeAnnulation, rejeterUneDemandeAnnulation } from './api'
import type { DemandeAnnulation } from './types'

/** api/app/Domain/Caisse/Enums/ModeDeReglement.php */
const MODES_DE_REMBOURSEMENT = ['especes', 'mobile_money', 'carte', 'virement', 'cheque', 'avance', 'canal_externe'] as const

interface Props {
  demande: DemandeAnnulation | null
  decision: 'acceptation' | 'rejet' | null
  onFermer: () => void
  onDecide: () => void
}

/** Décision sur une demande d'annulation (CdC § 6.1) : retenue / remboursement déjà calculés côté API, ici seulement la décision et son mode de remboursement. */
export function FormulaireDecisionAnnulation({ demande, decision, onFermer, onDecide }: Props) {
  const { t } = useTranslation()
  const [mode, setMode] = useState<string | undefined>(undefined)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (demande) {
      setMode(undefined)
      setMotif('')
      setErreur(null)
    }
  }, [demande])

  const decider = useMutation({
    mutationFn: () =>
      decision === 'acceptation'
        ? accepterUneDemandeAnnulation(demande!.id, mode, motif || undefined)
        : rejeterUneDemandeAnnulation(demande!.id, motif),
    onSuccess: onDecide,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const bloque = decision === 'rejet' && motif.trim().length < 5

  return (
    <Modal
      title={decision === 'acceptation' ? t('backoffice.demandesAnnulation.accepter') : t('backoffice.demandesAnnulation.rejeter')}
      open={demande !== null && decision !== null}
      onCancel={onFermer}
      onOk={() => decider.mutate()}
      confirmLoading={decider.isPending}
      okText={t('listes.confirmation.confirmer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: bloque, danger: decision === 'rejet' }}
      destroyOnHidden
      width={440}
    >
      <Form layout="vertical">
        {decision === 'acceptation' && (
          <Form.Item label={t('backoffice.reclamations.changements.modeDeRemboursement')} htmlFor="champ-mode-remboursement-annulation">
            <Select
              id="champ-mode-remboursement-annulation"
              size="large"
              allowClear
              value={mode}
              onChange={setMode}
              aria-label={t('backoffice.reclamations.changements.modeDeRemboursement')}
              options={MODES_DE_REMBOURSEMENT.map((m) => ({ value: m, label: t(`backoffice.reclamations.changements.modes.${m}`) }))}
            />
          </Form.Item>
        )}
        <Form.Item
          label={t('backoffice.reservations.detail.motif')}
          htmlFor="champ-motif-decision-annulation"
          required={decision === 'rejet'}
        >
          <Input.TextArea
            id="champ-motif-decision-annulation"
            rows={3}
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
            aria-label={t('backoffice.reservations.detail.motif')}
          />
        </Form.Item>
      </Form>
      {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
