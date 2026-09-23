import { useMutation, useQuery } from '@tanstack/react-query'
import { Alert, DatePicker, Form, Input, Select } from 'antd'
import dayjs, { type Dayjs } from 'dayjs'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { listerLesLogements, listerLesResidences } from '../catalogue/api'
import { demanderUnMenage } from './api'

interface Props {
  ouvert: boolean
  onFermer: () => void
  onDemande: () => void
}

/** Ménage demandé, ad hoc (CdC § 6.4) : sur un logement précis, pas forcément lié à un séjour. */
export function FormulaireDemandeMenage({ ouvert, onFermer, onDemande }: Props) {
  const { t } = useTranslation()
  const [residenceId, setResidenceId] = useState<number | undefined>(undefined)
  const [logementId, setLogementId] = useState<number | undefined>(undefined)
  const [motif, setMotif] = useState('')
  const [echeance, setEcheance] = useState<Dayjs | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const residences = useQuery({
    queryKey: ['backoffice', 'residences', 'toutes'],
    queryFn: () => listerLesResidences({ par_page: 100 }),
    enabled: ouvert,
  })
  const logements = useQuery({
    queryKey: ['backoffice', 'residences', residenceId, 'logements'],
    queryFn: () => listerLesLogements(residenceId!),
    enabled: ouvert && residenceId !== undefined,
  })

  useEffect(() => {
    if (ouvert) {
      setResidenceId(undefined)
      setLogementId(undefined)
      setMotif('')
      setEcheance(dayjs())
      setErreur(null)
    }
  }, [ouvert])

  const demander = useMutation({
    mutationFn: () => demanderUnMenage(logementId!, { motif, echeance: echeance!.format('YYYY-MM-DD') }),
    onSuccess: onDemande,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('backoffice.missions.demande.titre')}
      open={ouvert}
      onCancel={onFermer}
      onOk={() => demander.mutate()}
      confirmLoading={demander.isPending}
      okText={t('backoffice.missions.demande.action')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: !logementId || motif.trim().length < 5 || !echeance }}
      destroyOnHidden
      width={480}
    >
      <Form layout="vertical">
        <Form.Item label={t('backoffice.missions.demande.residence')} htmlFor="champ-residence-menage">
          <Select
            id="champ-residence-menage"
            size="large"
            showSearch
            optionFilterProp="label"
            loading={residences.isPending}
            value={residenceId}
            onChange={(v) => {
              setResidenceId(v)
              setLogementId(undefined)
            }}
            options={residences.data?.elements.map((r) => ({ value: r.id, label: r.nom }))}
          />
        </Form.Item>
        <Form.Item label={t('backoffice.missions.demande.logement')} htmlFor="champ-logement-menage">
          <Select
            id="champ-logement-menage"
            size="large"
            disabled={residenceId === undefined}
            loading={logements.isPending}
            value={logementId}
            onChange={setLogementId}
            options={logements.data?.map((l) => ({ value: l.id, label: l.nom }))}
          />
        </Form.Item>
        <Form.Item label={t('backoffice.missions.demande.echeance')} htmlFor="champ-echeance-menage">
          <DatePicker
            id="champ-echeance-menage"
            style={{ width: '100%' }}
            size="large"
            format="DD/MM/YYYY"
            value={echeance}
            onChange={setEcheance}
          />
        </Form.Item>
        <Form.Item label={t('backoffice.missions.demande.motif')} htmlFor="champ-motif-menage" required>
          <Input.TextArea
            id="champ-motif-menage"
            rows={3}
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
            aria-label={t('backoffice.missions.demande.motif')}
          />
        </Form.Item>
      </Form>
      {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
