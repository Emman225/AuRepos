import { useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Form, Input, InputNumber, Skeleton, Typography } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { Modal } from '../../shared/composants/PopupModal'
import { couleurs } from '../../shared/theme/jetons'
import { formaterPrix } from '../../shared/format/devise'
import { afficherLesConsommations, faireLeCheckOut } from './api'
import type { SejourAgent } from './types'

interface Props {
  ouvert: boolean
  sejour: SejourAgent | null
  onFermer: () => void
  onCheckOut: (sejour: SejourAgent) => void
}

/**
 * Check-out (CdC § 6.3) : consommations facturées, décision manuelle sur la caution. Mêmes textes
 * que le check-out back office (`backoffice.reservations.checkOut.*`) — même geste, même
 * vocabulaire, aucune raison de les dupliquer sous une autre clé.
 */
export function FormulaireCheckOut({ ouvert, sejour, onFermer, onCheckOut }: Props) {
  const { t } = useTranslation()
  const [cautionRetenue, setCautionRetenue] = useState<number>(0)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const consommations = useQuery({
    queryKey: ['agent', 'sejours', sejour?.id, 'consommations'],
    queryFn: () => afficherLesConsommations(sejour!.id),
    enabled: ouvert && sejour !== null,
  })

  useEffect(() => {
    if (ouvert) {
      setCautionRetenue(0)
      setMotif('')
      setErreur(null)
    }
  }, [ouvert])

  const checkOut = useMutation({
    mutationFn: () => faireLeCheckOut(sejour!.id, cautionRetenue, motif || undefined),
    onSuccess: onCheckOut,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const motifManquant = cautionRetenue > 0 && motif.trim().length < 5

  return (
    <Modal
      title={t('backoffice.reservations.checkOut.titre')}
      open={ouvert}
      onCancel={onFermer}
      onOk={() => checkOut.mutate()}
      confirmLoading={checkOut.isPending}
      okText={t('backoffice.reservations.checkOut.confirmer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: motifManquant }}
      destroyOnHidden
      width={520}
    >
      <Typography.Title level={5} style={{ marginTop: 0 }}>
        {t('backoffice.reservations.checkOut.consommations')}
      </Typography.Title>
      {consommations.isPending ? (
        <Skeleton active paragraph={{ rows: 2 }} />
      ) : consommations.data ? (
        <div style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 10, padding: '4px 18px' }}>
          {[
            { libelle: t('backoffice.reservations.checkOut.hebergement'), valeur: consommations.data.hebergement },
            { libelle: t('backoffice.reservations.checkOut.repas'), valeur: consommations.data.repas },
            { libelle: t('backoffice.reservations.checkOut.transferts'), valeur: consommations.data.transferts },
          ].map((ligne) => (
            <div key={ligne.libelle} style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0' }}>
              <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret }}>{ligne.libelle}</Typography.Text>
              <Typography.Text style={{ fontSize: 13, fontVariantNumeric: 'tabular-nums' }}>{formaterPrix(ligne.valeur)}</Typography.Text>
            </div>
          ))}
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderTop: `1px solid ${couleurs.bordure}` }}>
            <Typography.Text strong style={{ fontSize: 13 }}>
              {t('backoffice.reservations.checkOut.total')}
            </Typography.Text>
            <Typography.Text strong style={{ fontSize: 13, fontVariantNumeric: 'tabular-nums' }}>
              {formaterPrix(consommations.data.total)}
            </Typography.Text>
          </div>
        </div>
      ) : (
        <Typography.Text type="secondary">{t('backoffice.reservations.checkOut.aucuneConsommation')}</Typography.Text>
      )}

      <Form layout="vertical" style={{ marginTop: 16 }}>
        <Form.Item label={t('backoffice.reservations.checkOut.cautionRetenue')} htmlFor="champ-caution-retenue-agent">
          <InputNumber
            id="champ-caution-retenue-agent"
            style={{ width: '100%' }}
            size="large"
            min={0}
            step={1000}
            addonAfter="F"
            value={cautionRetenue}
            onChange={(v) => setCautionRetenue(v ?? 0)}
            aria-label={t('backoffice.reservations.checkOut.cautionRetenue')}
          />
        </Form.Item>
        <Form.Item
          label={t('backoffice.reservations.checkOut.motif')}
          htmlFor="champ-motif-caution-agent"
          required={cautionRetenue > 0}
          validateStatus={motifManquant ? 'error' : undefined}
          help={motifManquant ? t('backoffice.reservations.checkOut.motifObligatoire') : undefined}
        >
          <Input.TextArea
            id="champ-motif-caution-agent"
            rows={3}
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
            aria-label={t('backoffice.reservations.checkOut.motif')}
          />
        </Form.Item>
      </Form>
      {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
