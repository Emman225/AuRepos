import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Col, DatePicker, Form, InputNumber, Row, Select } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { reserverManuellement } from './api'
import type { ReservationBackOffice } from './types'

// Confort de saisie seulement (api/app/Http/Requests/Sejours/ReservationManuelleRequest.php) : le serveur seul décide.
const schema = z
  .object({
    canal: z.enum(['telephone', 'walk_in', 'canal_externe']),
    nom: z.string().trim().min(1, 'backoffice.reservations.manuelle.nomObligatoire').max(100),
    email: z.string().trim().min(1, 'backoffice.reservations.manuelle.courrielObligatoire').email('inscription.courrielInvalide'),
    telephone: z.string().trim().max(30),
    reference_logement: z.string().trim().min(1, 'backoffice.reservations.manuelle.logementObligatoire').max(40),
    periode: z.tuple([z.custom<dayjs.Dayjs>(), z.custom<dayjs.Dayjs>()]).nullable(),
    adultes: z.number().min(1).max(60),
    enfants: z.number().min(0).max(60),
    mode_reglement: z.enum(['en_ligne', 'agence', 'a_terme']),
    bon_de_commande: z.string().trim().max(100),
  })
  .refine((s) => s.periode !== null, { path: ['periode'], message: 'backoffice.reservations.manuelle.datesObligatoires' })
type Saisie = z.infer<typeof schema>

const CHAMPS = ['canal', 'nom', 'email', 'telephone', 'reference_logement', 'periode', 'adultes', 'enfants', 'mode_reglement', 'bon_de_commande'] as const

interface Props {
  ouvert: boolean
  onFermer: () => void
  onCree: (reservation: ReservationBackOffice) => void
}

/** Réservation manuelle par la réception (CdC § 6.1) : téléphone, walk-in, canal externe. */
export function FormulaireReservationManuelle({ ouvert, onFermer, onCree }: Props) {
  const { t } = useTranslation()
  const [erreur, setErreur] = useState<string | null>(null)
  const {
    control,
    handleSubmit,
    reset,
    setError,
    formState: { isSubmitting },
  } = useForm<Saisie>({
    resolver: zodResolver(schema),
    defaultValues: {
      canal: 'telephone', nom: '', email: '', telephone: '', reference_logement: '',
      periode: null, adultes: 2, enfants: 0, mode_reglement: 'agence', bon_de_commande: '',
    },
  })

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const reservation = await reserverManuellement({
        canal: saisie.canal,
        client: { nom: saisie.nom, email: saisie.email, telephone: saisie.telephone || undefined },
        reference_logement: saisie.reference_logement,
        arrivee: saisie.periode![0].format('YYYY-MM-DD'),
        depart: saisie.periode![1].format('YYYY-MM-DD'),
        adultes: saisie.adultes,
        enfants: saisie.enfants,
        mode_reglement: saisie.mode_reglement,
        bon_de_commande: saisie.bon_de_commande || undefined,
      })
      reset()
      onCree(reservation)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={t('backoffice.reservations.manuelle.titre')}
      open={ouvert}
      onCancel={() => {
        reset()
        setErreur(null)
        onFermer()
      }}
      onOk={() => void soumettre()}
      confirmLoading={isSubmitting}
      okText={t('backoffice.reservations.manuelle.enregistrer')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
    >
      <Form layout="vertical">
        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="canal"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('backoffice.reservations.manuelle.canal')}>
                  <Select
                    {...field}
                    size="large"
                    options={[
                      { value: 'telephone', label: t('backoffice.reservations.manuelle.canalTelephone') },
                      { value: 'walk_in', label: t('backoffice.reservations.manuelle.canalWalkIn') },
                      { value: 'canal_externe', label: t('backoffice.reservations.manuelle.canalExterne') },
                    ]}
                  />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={12}>
            <Controller
              name="mode_reglement"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('tunnel.reglement.titre')}>
                  <Select
                    {...field}
                    size="large"
                    options={[
                      { value: 'en_ligne', label: t('tunnel.reglement.enLigne') },
                      { value: 'agence', label: t('tunnel.reglement.agence') },
                      { value: 'a_terme', label: t('tunnel.reglement.aTerme') },
                    ]}
                  />
                </Form.Item>
              )}
            />
          </Col>
        </Row>

        <Champ control={control} nom="nom" libelle={t('inscription.nom')} obligatoire />
        <Row gutter={12}>
          <Col span={12}>
            <Champ control={control} nom="email" libelle={t('inscription.courriel')} obligatoire inputMode="email" />
          </Col>
          <Col span={12}>
            <Champ control={control} nom="telephone" libelle={t('inscription.telephone')} inputMode="tel" />
          </Col>
        </Row>

        <Champ control={control} nom="reference_logement" libelle={t('backoffice.reservations.manuelle.logement')} obligatoire aide={t('backoffice.reservations.manuelle.logementAide')} />

        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="periode"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('backoffice.reservations.manuelle.periode')}
                  required
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <DatePicker.RangePicker
                    style={{ width: '100%' }}
                    size="large"
                    format="DD/MM/YYYY"
                    placeholder={[t('tunnel.arrivee'), t('tunnel.depart')]}
                    value={field.value}
                    onChange={(v) => field.onChange(v && v[0] && v[1] ? [v[0], v[1]] : null)}
                    disabledDate={(d) => d.isBefore(dayjs().startOf('day'))}
                  />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={6}>
            <Controller
              name="adultes"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('tunnel.adultes')}>
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={1} max={60} />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={6}>
            <Controller
              name="enfants"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('tunnel.enfants')}>
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={0} max={60} />
                </Form.Item>
              )}
            />
          </Col>
        </Row>

        <Champ control={control} nom="bon_de_commande" libelle={t('backoffice.reservations.manuelle.bonDeCommande')} aide={t('backoffice.reservations.manuelle.bonDeCommandeAide')} />

        {erreur && <Alert type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
