import { useMutation } from '@tanstack/react-query'
import { Alert, Form, Input, Typography } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { Modal } from '../../shared/composants/PopupModal'
import { faireLeCheckIn } from './api'
import type { SejourAgent } from './types'

interface Props {
  ouvert: boolean
  sejour: SejourAgent | null
  onFermer: () => void
  onCheckIn: (sejour: SejourAgent) => void
}

/**
 * Check-in (CdC § 6.3, § 11) : le code d'arrivée est saisi par l'agent, jamais lu ni affiché.
 * Mêmes textes que le check-in back office (`backoffice.reservations.checkIn.*`) — même geste,
 * même vocabulaire, aucune raison de les dupliquer sous une autre clé.
 */
export function FormulaireCheckIn({ ouvert, sejour, onFermer, onCheckIn }: Props) {
  const { t } = useTranslation()
  const [code, setCode] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (ouvert) {
      setCode('')
      setErreur(null)
    }
  }, [ouvert])

  const checkIn = useMutation({
    mutationFn: () => faireLeCheckIn(sejour!.id, code),
    onSuccess: onCheckIn,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('backoffice.reservations.checkIn.titre')}
      open={ouvert}
      onCancel={onFermer}
      onOk={() => checkIn.mutate()}
      confirmLoading={checkIn.isPending}
      okText={t('backoffice.reservations.checkIn.confirmer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: code.trim().length === 0 }}
      destroyOnHidden
      width={420}
    >
      <Typography.Paragraph type="secondary">{t('backoffice.reservations.checkIn.aide')}</Typography.Paragraph>
      <Form layout="vertical">
        <Form.Item label={t('backoffice.reservations.checkIn.code')} htmlFor="champ-code-arrivee-agent" required>
          <Input
            id="champ-code-arrivee-agent"
            size="large"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            aria-label={t('backoffice.reservations.checkIn.code')}
            autoFocus
          />
        </Form.Item>
      </Form>
      {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
