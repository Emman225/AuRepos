import { useMutation } from '@tanstack/react-query'
import { Alert, Form, Input, Select } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { deciderUnChangement } from './api'
import type { ChangementAValider } from './types'

/** api/app/Domain/Caisse/Enums/ModeDeReglement.php — valeurs exactes, aucun libellé côté API pour ce sélecteur. */
const MODES_DE_REMBOURSEMENT = ['especes', 'mobile_money', 'carte', 'virement', 'cheque', 'avance', 'canal_externe'] as const

interface Props {
  changement: ChangementAValider | null
  decision: 'valider' | 'refuser' | null
  onFermer: () => void
  onDecide: () => void
}

/** Confirmation du trésorier désigné (double validation, CdC § 6.4) : `mode_de_remboursement` est exigé pour valider un avoir. */
export function FormulaireDecisionChangement({ changement, decision, onFermer, onDecide }: Props) {
  const { t } = useTranslation()
  const [mode, setMode] = useState<string | undefined>(undefined)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (changement) {
      setMode(undefined)
      setMotif('')
      setErreur(null)
    }
  }, [changement])

  const decider = useMutation({
    mutationFn: () => deciderUnChangement(changement!.id, decision!, motif || undefined, decision === 'valider' ? mode : undefined),
    onSuccess: onDecide,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const bloque = decision === 'valider' ? !mode : motif.trim().length < 5

  return (
    <Modal
      title={decision === 'valider' ? t('backoffice.reclamations.changements.validerTitre') : t('backoffice.reclamations.changements.refuserTitre')}
      open={changement !== null && decision !== null}
      onCancel={onFermer}
      onOk={() => decider.mutate()}
      confirmLoading={decider.isPending}
      okText={t('listes.confirmation.confirmer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: bloque, danger: decision === 'refuser' }}
      destroyOnHidden
      width={420}
    >
      <Form layout="vertical">
        {decision === 'valider' ? (
          <Form.Item label={t('backoffice.reclamations.changements.modeDeRemboursement')} htmlFor="champ-mode-remboursement" required>
            <Select
              id="champ-mode-remboursement"
              size="large"
              value={mode}
              onChange={setMode}
              aria-label={t('backoffice.reclamations.changements.modeDeRemboursement')}
              options={MODES_DE_REMBOURSEMENT.map((m) => ({ value: m, label: t(`backoffice.reclamations.changements.modes.${m}`) }))}
            />
          </Form.Item>
        ) : (
          <Form.Item label={t('backoffice.reservations.detail.motif')} htmlFor="champ-motif-refus" required>
            <Input.TextArea
              id="champ-motif-refus"
              rows={3}
              value={motif}
              onChange={(e) => setMotif(e.target.value)}
              aria-label={t('backoffice.reservations.detail.motif')}
            />
          </Form.Item>
        )}
      </Form>
      {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
