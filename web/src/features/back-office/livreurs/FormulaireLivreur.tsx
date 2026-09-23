import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Checkbox, Col, Form, Row } from 'antd'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { creerUnLivreur, modifierLeLivreur } from './api'
import type { Livreur } from './types'

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
  livreur?: Livreur | null
  onFermer: () => void
  onEnregistre: (livreur: Livreur) => void
}

/** Création ou modification d'un livreur de repas : compte de connexion, sans véhicule (CdC — « Repas et boissons »). */
export function FormulaireLivreur({ ouvert, livreur, onFermer, onEnregistre }: Props) {
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
      livreur
        ? {
            nom: livreur.compte.nom,
            prenoms: livreur.compte.prenoms ?? '',
            email: livreur.compte.email,
            telephone: livreur.compte.telephone ?? '',
            actif: livreur.actif,
          }
        : { nom: '', prenoms: '', email: '', telephone: '', actif: true },
    )
    setErreur(null)
  }, [ouvert, livreur, reset])

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
      const enregistre = livreur ? await modifierLeLivreur(livreur.id, payload) : await creerUnLivreur(payload)
      onEnregistre(enregistre)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={livreur ? t('backoffice.livreurs.modifier') : t('backoffice.livreurs.creer')}
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

        {livreur && (
          <Row gutter={12}>
            <Col span={12} style={{ display: 'flex', alignItems: 'center' }}>
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
          </Row>
        )}

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
