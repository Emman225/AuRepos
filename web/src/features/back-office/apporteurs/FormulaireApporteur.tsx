import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Checkbox, Col, Form, InputNumber, Row } from 'antd'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { creerUnApporteur, modifierLApporteur } from './api'
import type { Apporteur } from './types'

const schema = z.object({
  nom: z.string().trim().min(1, 'backoffice.proprietaires.nomObligatoire').max(100),
  prenoms: z.string().trim().max(150),
  email: z.string().trim().min(1, 'backoffice.proprietaires.courrielObligatoire').email('inscription.courrielInvalide'),
  telephone: z.string().trim().max(30),
  pourcentage: z.number().min(0).max(100),
  actif: z.boolean(),
})
type Saisie = z.infer<typeof schema>

const CHAMPS = ['nom', 'prenoms', 'email', 'telephone', 'pourcentage'] as const

interface Props {
  ouvert: boolean
  apporteur?: Apporteur | null
  onFermer: () => void
  onEnregistre: (apporteur: Apporteur) => void
}

/** Création ou modification d'un apporteur d'affaires : compte de connexion + pourcentage de commission (CdC — apporteurs). */
export function FormulaireApporteur({ ouvert, apporteur, onFermer, onEnregistre }: Props) {
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
    defaultValues: { nom: '', prenoms: '', email: '', telephone: '', pourcentage: 10, actif: true },
  })

  useEffect(() => {
    if (!ouvert) return
    reset(
      apporteur
        ? {
            nom: apporteur.compte.nom,
            prenoms: apporteur.compte.prenoms ?? '',
            email: apporteur.compte.email,
            telephone: apporteur.compte.telephone ?? '',
            pourcentage: apporteur.pourcentage,
            actif: apporteur.actif,
          }
        : { nom: '', prenoms: '', email: '', telephone: '', pourcentage: 10, actif: true },
    )
    setErreur(null)
  }, [ouvert, apporteur, reset])

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const payload = {
        nom: saisie.nom,
        prenoms: saisie.prenoms || undefined,
        email: saisie.email,
        telephone: saisie.telephone || undefined,
        pourcentage: saisie.pourcentage,
        actif: saisie.actif,
      }
      const enregistre = apporteur ? await modifierLApporteur(apporteur.id, payload) : await creerUnApporteur(payload)
      onEnregistre(enregistre)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={apporteur ? t('backoffice.apporteurs.modifier') : t('backoffice.apporteurs.creer')}
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
      width={520}
    >
      <Form layout="vertical">
        <Row gutter={12}>
          <Col span={12}>
            <Champ control={control} nom="nom" libelle={t('inscription.nom')} obligatoire />
          </Col>
          <Col span={12}>
            <Champ control={control} nom="prenoms" libelle={t('inscription.prenoms')} />
          </Col>
        </Row>
        <Row gutter={12}>
          <Col span={12}>
            <Champ control={control} nom="email" libelle={t('inscription.courriel')} obligatoire inputMode="email" />
          </Col>
          <Col span={12}>
            <Champ control={control} nom="telephone" libelle={t('inscription.telephone')} inputMode="tel" />
          </Col>
        </Row>

        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="pourcentage"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('backoffice.apporteurs.pourcentage')}
                  required
                  validateStatus={fieldState.error ? 'error' : undefined}
                >
                  <InputNumber {...field} style={{ width: '100%' }} size="large" min={0} max={100} />
                </Form.Item>
              )}
            />
          </Col>
          {apporteur && (
            <Col span={12} style={{ display: 'flex', alignItems: 'center', marginTop: 30 }}>
              <Controller
                name="actif"
                control={control}
                render={({ field }) => (
                  <Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)}>
                    {t('backoffice.apporteurs.actif')}
                  </Checkbox>
                )}
              />
            </Col>
          )}
        </Row>

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
