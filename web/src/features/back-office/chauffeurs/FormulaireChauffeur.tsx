import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Checkbox, Col, Form, Row } from 'antd'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { creerUnChauffeur, modifierLeChauffeur } from './api'
import type { Chauffeur } from './types'

const schema = z.object({
  nom: z.string().trim().min(1, 'backoffice.proprietaires.nomObligatoire').max(100),
  prenoms: z.string().trim().max(150),
  email: z.string().trim().min(1, 'backoffice.proprietaires.courrielObligatoire').email('inscription.courrielInvalide'),
  telephone: z.string().trim().max(30),
  actif: z.boolean(),
})
type Saisie = z.infer<typeof schema>

const CHAMPS = ['nom', 'prenoms', 'email', 'telephone'] as const

interface Props {
  ouvert: boolean
  chauffeur?: Chauffeur | null
  onFermer: () => void
  onEnregistre: (chauffeur: Chauffeur) => void
}

/** Création ou modification d'un chauffeur (CdC § 6.6) : même patron que FormulaireApporteur, sans pourcentage. */
export function FormulaireChauffeur({ ouvert, chauffeur, onFermer, onEnregistre }: Props) {
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
    defaultValues: { nom: '', prenoms: '', email: '', telephone: '', actif: true },
  })

  useEffect(() => {
    if (!ouvert) return
    reset(
      chauffeur
        ? {
            nom: chauffeur.compte.nom,
            prenoms: chauffeur.compte.prenoms ?? '',
            email: chauffeur.compte.email,
            telephone: chauffeur.compte.telephone ?? '',
            actif: chauffeur.actif,
          }
        : { nom: '', prenoms: '', email: '', telephone: '', actif: true },
    )
    setErreur(null)
  }, [ouvert, chauffeur, reset])

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const payload = {
        nom: saisie.nom,
        prenoms: saisie.prenoms || undefined,
        email: saisie.email,
        telephone: saisie.telephone || undefined,
        actif: saisie.actif,
      }
      const enregistre = chauffeur ? await modifierLeChauffeur(chauffeur.id, payload) : await creerUnChauffeur(payload)
      onEnregistre(enregistre)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={chauffeur ? t('backoffice.chauffeurs.modifier') : t('backoffice.chauffeurs.creer')}
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

        {chauffeur && (
          <Controller
            name="actif"
            control={control}
            render={({ field }) => (
              <Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)}>
                {t('backoffice.chauffeurs.actif')}
              </Checkbox>
            )}
          />
        )}

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
