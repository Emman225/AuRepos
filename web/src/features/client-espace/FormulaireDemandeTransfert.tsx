import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { Alert, Col, DatePicker, Form, InputNumber, Row, Select } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { lire } from '../../shared/api/client'
import { Modal } from '../../shared/composants/PopupModal'
import { Champ } from '../../shared/formulaires/Champ'
import { reporterErreurs } from '../../shared/formulaires/reporterErreurs'
import type { CommuneChoix, TypeVehiculeChoix } from '../site-public/types'
import { demanderUnTransfert } from './api'
import type { TransfertClient } from './types'

const schema = z.object({
  lieu_de_prise_en_charge: z.string().trim().min(1, 'client.transferts.lieuObligatoire').max(255),
  commune_id: z.number({ error: 'client.transferts.communeObligatoire' }),
  type_vehicule_souhaite_id: z.number({ error: 'client.transferts.typeVehiculeObligatoire' }),
  date_heure_prevue: z.custom<dayjs.Dayjs>((v) => dayjs.isDayjs(v), 'client.transferts.dateHeureObligatoire'),
  nombre_passagers: z.number({ error: 'client.transferts.passagersObligatoire' }).min(1).max(50),
  nombre_bagages: z.number().min(0).max(50),
  notes: z.string().trim().max(1000),
})
type Saisie = z.infer<typeof schema>

const CHAMPS = ['lieu_de_prise_en_charge', 'commune_id', 'type_vehicule_souhaite_id', 'date_heure_prevue', 'nombre_passagers', 'nombre_bagages', 'notes'] as const

interface Props {
  ouvert: boolean
  reference: string
  onFermer: () => void
  onDemande: (transfert: TransfertClient) => void
}

/** Espace client › Détail d'un séjour › Demander un transfert (CdC § 5.2, § 6.6) : le montant affiché après soumission vient toujours du serveur (barème), jamais deviné à l'écran. */
export function FormulaireDemandeTransfert({ ouvert, reference, onFermer, onDemande }: Props) {
  const { t } = useTranslation()
  const [erreur, setErreur] = useState<string | null>(null)

  const communes = useQuery({
    queryKey: ['referentiels', 'communes'],
    queryFn: () => lire<CommuneChoix[]>('/referentiels/communes'),
    enabled: ouvert,
  })
  const typesVehicule = useQuery({
    queryKey: ['referentiels', 'types-vehicule'],
    queryFn: () => lire<TypeVehiculeChoix[]>('/referentiels/types-vehicule'),
    enabled: ouvert,
  })

  const {
    control,
    handleSubmit,
    reset,
    setError,
    formState: { isSubmitting },
  } = useForm<Saisie>({
    resolver: zodResolver(schema),
    defaultValues: {
      lieu_de_prise_en_charge: '',
      commune_id: undefined,
      type_vehicule_souhaite_id: undefined,
      date_heure_prevue: undefined,
      nombre_passagers: 1,
      nombre_bagages: 0,
      notes: '',
    },
  })

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const demande = await demanderUnTransfert(reference, {
        lieu_de_prise_en_charge: saisie.lieu_de_prise_en_charge,
        commune_id: saisie.commune_id,
        type_vehicule_souhaite_id: saisie.type_vehicule_souhaite_id,
        date_heure_prevue: saisie.date_heure_prevue.format('YYYY-MM-DD HH:mm'),
        nombre_passagers: saisie.nombre_passagers,
        nombre_bagages: saisie.nombre_bagages || undefined,
        notes: saisie.notes || undefined,
      })
      reset()
      onDemande(demande)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={t('client.transferts.demander')}
      open={ouvert}
      onCancel={() => {
        reset()
        setErreur(null)
        onFermer()
      }}
      onOk={() => void soumettre()}
      confirmLoading={isSubmitting}
      okText={t('client.transferts.demander')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
      width={520}
    >
      <Form layout="vertical">
        <Champ control={control} nom="lieu_de_prise_en_charge" libelle={t('client.transferts.lieu')} obligatoire />

        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="commune_id"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('client.transferts.commune')}
                  htmlFor="champ-commune_id"
                  required
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <Select
                    {...field}
                    id="champ-commune_id"
                    size="large"
                    loading={communes.isPending}
                    options={communes.data?.map((c) => ({ value: c.id, label: c.nom }))}
                  />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={12}>
            <Controller
              name="type_vehicule_souhaite_id"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('client.transferts.typeVehicule')}
                  htmlFor="champ-type_vehicule_souhaite_id"
                  required
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <Select
                    {...field}
                    id="champ-type_vehicule_souhaite_id"
                    size="large"
                    loading={typesVehicule.isPending}
                    options={typesVehicule.data?.map((v) => ({ value: v.id, label: v.nom }))}
                  />
                </Form.Item>
              )}
            />
          </Col>
        </Row>

        <Controller
          name="date_heure_prevue"
          control={control}
          render={({ field, fieldState }) => (
            <Form.Item
              label={t('client.transferts.dateHeure')}
              required
              validateStatus={fieldState.error ? 'error' : undefined}
              help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
            >
              <DatePicker
                value={field.value ?? null}
                onChange={field.onChange}
                showTime={{ format: 'HH:mm' }}
                format="DD/MM/YYYY HH:mm"
                style={{ width: '100%' }}
                size="large"
              />
            </Form.Item>
          )}
        />

        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="nombre_passagers"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('client.transferts.passagers')}
                  required
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={1} max={50} />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={12}>
            <Controller
              name="nombre_bagages"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('client.transferts.bagages')}>
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={0} max={50} />
                </Form.Item>
              )}
            />
          </Col>
        </Row>

        <Champ control={control} nom="notes" libelle={t('client.transferts.notes')} />

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
