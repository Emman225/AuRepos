import { useMutation } from '@tanstack/react-query'
import { Alert, DatePicker, Form } from 'antd'
import dayjs, { type Dayjs } from 'dayjs'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { modifierLeDepart } from './api'
import type { ReservationBackOffice } from './types'

interface Props {
  ouvert: boolean
  sejour: ReservationBackOffice | null
  onFermer: () => void
  onModifie: (sejour: ReservationBackOffice) => void
}

/** Prolongation / départ anticipé (CdC § 6.1) : contrôle de disponibilité et recalcul restent côté API. */
export function FormulaireProlongation({ ouvert, sejour, onFermer, onModifie }: Props) {
  const { t } = useTranslation()
  const [depart, setDepart] = useState<Dayjs | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (ouvert && sejour) {
      setDepart(dayjs(sejour.depart))
      setErreur(null)
    }
  }, [ouvert, sejour])

  const modifier = useMutation({
    mutationFn: () => modifierLeDepart(sejour!.id, depart!.format('YYYY-MM-DD')),
    onSuccess: onModifie,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('backoffice.reservations.prolongation.titre')}
      open={ouvert}
      onCancel={onFermer}
      onOk={() => modifier.mutate()}
      confirmLoading={modifier.isPending}
      okText={t('backoffice.reservations.prolongation.enregistrer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: !depart }}
      destroyOnHidden
      width={420}
    >
      <Form layout="vertical">
        <Form.Item label={t('backoffice.reservations.prolongation.nouveauDepart')} htmlFor="champ-nouveau-depart">
          <DatePicker
            id="champ-nouveau-depart"
            style={{ width: '100%' }}
            size="large"
            format="DD/MM/YYYY"
            placeholder={t('backoffice.reservations.prolongation.nouveauDepart')}
            value={depart}
            onChange={setDepart}
          />
        </Form.Item>
      </Form>
      {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
