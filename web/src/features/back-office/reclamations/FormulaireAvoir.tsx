import { useMutation } from '@tanstack/react-query'
import { Alert, Form, Input, InputNumber } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { proposerUnAvoir } from './api'
import type { Reclamation } from './types'

interface Props {
  reclamation: Reclamation | null
  onFermer: () => void
  onPropose: () => void
}

/** Propose seulement (CdC § 6.4) : la confirmation par le trésorier désigné vient ensuite, via la file des changements. */
export function FormulaireAvoir({ reclamation, onFermer, onPropose }: Props) {
  const { t } = useTranslation()
  const [montant, setMontant] = useState<number | null>(null)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (reclamation) {
      setMontant(null)
      setMotif('')
      setErreur(null)
    }
  }, [reclamation])

  const proposer = useMutation({
    mutationFn: () => proposerUnAvoir(reclamation!.id, montant!, motif),
    onSuccess: onPropose,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('backoffice.reclamations.avoir.titre')}
      open={reclamation !== null}
      onCancel={onFermer}
      onOk={() => proposer.mutate()}
      confirmLoading={proposer.isPending}
      okText={t('backoffice.reclamations.avoir.proposer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: !montant || motif.trim().length < 5 }}
      destroyOnHidden
      width={440}
    >
      <Form layout="vertical">
        <Form.Item label={t('backoffice.reclamations.avoir.montant')} htmlFor="champ-montant-avoir">
          <InputNumber
            id="champ-montant-avoir"
            style={{ width: '100%' }}
            size="large"
            min={1}
            step={1000}
            addonAfter="F"
            value={montant}
            onChange={setMontant}
            aria-label={t('backoffice.reclamations.avoir.montant')}
          />
        </Form.Item>
        <Form.Item label={t('backoffice.reservations.detail.motif')} htmlFor="champ-motif-avoir" required>
          <Input.TextArea
            id="champ-motif-avoir"
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
