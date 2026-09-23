import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { Alert, Col, Form, InputNumber, Row, Select } from 'antd'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { lire } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { creerUnLogement, modifierLeLogement } from './api'
import type { Logement } from './types'

interface TypeLogementChoix {
  id: number
  nom: string
}

const schema = z
  .object({
    type_logement_id: z.number({ error: 'backoffice.catalogue.logement.typeObligatoire' }),
    nom: z.string().trim().min(2, 'backoffice.catalogue.logement.nomCourt').max(150),
    nombre_pieces: z.number().min(1).max(30),
    nombre_chambres: z.number().min(0).max(30),
    capacite_de_base: z.number().min(1).max(60),
    capacite_maximale: z.number().min(1).max(60),
    caution: z.number().min(0),
    description: z.string().trim().max(5000),
  })
  .refine((s) => s.capacite_maximale >= s.capacite_de_base, {
    path: ['capacite_maximale'],
    message: 'backoffice.catalogue.logement.capaciteIncoherente',
  })
  .refine((s) => s.nombre_chambres <= s.nombre_pieces, {
    path: ['nombre_chambres'],
    message: 'backoffice.catalogue.logement.chambresIncoherentes',
  })
type Saisie = z.infer<typeof schema>

const CHAMPS = ['type_logement_id', 'nom', 'nombre_pieces', 'nombre_chambres', 'capacite_de_base', 'capacite_maximale', 'caution', 'description'] as const

interface Props {
  ouvert: boolean
  residenceId: number
  logement?: Logement | null
  onFermer: () => void
  onEnregistre: (logement: Logement) => void
}

/** Création ou modification d'un logement (CdC § 7.1). Prix et publication ont leurs propres écrans. */
export function FormulaireLogement({ ouvert, residenceId, logement, onFermer, onEnregistre }: Props) {
  const { t } = useTranslation()
  const [erreur, setErreur] = useState<string | null>(null)

  const types = useQuery({
    queryKey: ['referentiels', 'types-logement'],
    queryFn: () => lire<TypeLogementChoix[]>('/referentiels/types-logement'),
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
    defaultValues: { type_logement_id: undefined, nom: '', nombre_pieces: 1, nombre_chambres: 0, capacite_de_base: 1, capacite_maximale: 2, caution: 0, description: '' },
  })

  useEffect(() => {
    if (!ouvert) return
    reset(
      logement
        ? {
            type_logement_id: logement.type.id,
            nom: logement.nom,
            nombre_pieces: logement.nombre_pieces,
            nombre_chambres: logement.nombre_chambres ?? 0,
            capacite_de_base: logement.capacite_de_base,
            capacite_maximale: logement.capacite_maximale,
            caution: logement.caution,
            description: logement.description ?? '',
          }
        : { type_logement_id: undefined, nom: '', nombre_pieces: 1, nombre_chambres: 0, capacite_de_base: 1, capacite_maximale: 2, caution: 0, description: '' },
    )
    setErreur(null)
  }, [ouvert, logement, reset])

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const enregistre = logement ? await modifierLeLogement(residenceId, logement.id, saisie) : await creerUnLogement(residenceId, saisie)
      onEnregistre(enregistre)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={logement ? t('backoffice.catalogue.logement.modifier') : t('backoffice.catalogue.logement.creer')}
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
        <Champ control={control} nom="nom" libelle={t('backoffice.catalogue.logement.nom')} obligatoire />

        <Controller
          name="type_logement_id"
          control={control}
          render={({ field, fieldState }) => (
            <Form.Item
              label={t('backoffice.catalogue.logement.type')}
              htmlFor="champ-type_logement_id"
              required
              validateStatus={fieldState.error ? 'error' : undefined}
              help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
            >
              <Select
                {...field}
                id="champ-type_logement_id"
                size="large"
                loading={types.isPending}
                options={types.data?.map((v) => ({ value: v.id, label: v.nom }))}
              />
            </Form.Item>
          )}
        />

        <Row gutter={12}>
          <Col span={8}>
            <Controller
              name="nombre_pieces"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item label={t('backoffice.catalogue.logement.nombrePieces')} validateStatus={fieldState.error ? 'error' : undefined}>
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={1} max={30} />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={8}>
            <Controller
              name="nombre_chambres"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('backoffice.catalogue.logement.nombreChambres')}
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={0} max={30} />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={8}>
            <Controller
              name="caution"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('backoffice.catalogue.logement.caution')}>
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={0} step={1000} />
                </Form.Item>
              )}
            />
          </Col>
        </Row>

        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="capacite_de_base"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('backoffice.catalogue.logement.capaciteDeBase')}>
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={1} max={60} />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={12}>
            <Controller
              name="capacite_maximale"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('backoffice.catalogue.logement.capaciteMaximale')}
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={1} max={60} />
                </Form.Item>
              )}
            />
          </Col>
        </Row>

        <Champ control={control} nom="description" libelle={t('backoffice.catalogue.logement.description')} />

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
