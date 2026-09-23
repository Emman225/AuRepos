import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Checkbox, Col, Form, Row } from 'antd'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { creerUnRestaurateur, modifierLeRestaurateur } from './api'
import type { Restaurateur } from './types'

const schema = z.object({
  nom: z.string().trim().min(1, 'backoffice.proprietaires.nomObligatoire').max(100),
  prenoms: z.string().trim().max(150),
  email: z.string().trim().min(1, 'backoffice.proprietaires.courrielObligatoire').email('inscription.courrielInvalide'),
  telephone: z.string().trim().max(30),
  assujetti_tva: z.boolean(),
  actif: z.boolean(),
})
type Saisie = z.infer<typeof schema>

const CHAMPS = ['nom', 'prenoms', 'email', 'telephone'] as const

interface Props {
  ouvert: boolean
  restaurateur?: Restaurateur | null
  onFermer: () => void
  onEnregistre: (restaurateur: Restaurateur) => void
}

/**
 * Création ou modification d'un restaurateur : compte de connexion + assujettissement TVA
 * (CdC — « Repas et boissons »). `pourcentage_plateforme` n'y figure PAS : il se propose et
 * se valide séparément par double validation (voir FormulaireRestaurateur ne l'affiche jamais,
 * PageRestaurateurs porte le bloc de proposition — même patron que le pourcentage entreprise
 * global de la tarification, OngletPourcentageEntreprise).
 */
export function FormulaireRestaurateur({ ouvert, restaurateur, onFermer, onEnregistre }: Props) {
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
    defaultValues: { nom: '', prenoms: '', email: '', telephone: '', assujetti_tva: false, actif: true },
  })

  useEffect(() => {
    if (!ouvert) return
    reset(
      restaurateur
        ? {
            nom: restaurateur.compte.nom,
            prenoms: restaurateur.compte.prenoms ?? '',
            email: restaurateur.compte.email,
            telephone: restaurateur.compte.telephone ?? '',
            assujetti_tva: restaurateur.assujetti_tva,
            actif: restaurateur.actif,
          }
        : { nom: '', prenoms: '', email: '', telephone: '', assujetti_tva: false, actif: true },
    )
    setErreur(null)
  }, [ouvert, restaurateur, reset])

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const payload = {
        nom: saisie.nom,
        prenoms: saisie.prenoms || undefined,
        email: saisie.email,
        telephone: saisie.telephone || undefined,
        assujetti_tva: saisie.assujetti_tva,
        actif: saisie.actif,
      }
      const enregistre = restaurateur ? await modifierLeRestaurateur(restaurateur.id, payload) : await creerUnRestaurateur(payload)
      onEnregistre(enregistre)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={restaurateur ? t('backoffice.restaurateurs.modifier') : t('backoffice.restaurateurs.creer')}
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
          <Col span={12} style={{ display: 'flex', alignItems: 'center' }}>
            <Controller
              name="assujetti_tva"
              control={control}
              render={({ field }) => (
                <Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)}>
                  {t('backoffice.proprietaires.assujettiTva')}
                </Checkbox>
              )}
            />
          </Col>
          {restaurateur && (
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
          )}
        </Row>

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
