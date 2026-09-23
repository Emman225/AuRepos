import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { Alert, Checkbox, Col, Form, Row, Select } from 'antd'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { lire } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import type { CommuneChoix, QuartierChoix } from '../../site-public/types'
import { creerUneResidence, modifierLaResidence } from './api'
import type { Residence } from './types'

interface ProprietaireChoix {
  id: number
  nom_affiche: string
}

// Confort de saisie seulement (api/app/Http/Requests/Catalogue/ResidenceRequest.php) : le serveur seul décide.
const schema = z.object({
  proprietaire_id: z.number({ error: 'backoffice.catalogue.residence.proprietaireObligatoire' }),
  commune_id: z.number().optional(),
  quartier_id: z.number({ error: 'backoffice.catalogue.residence.quartierObligatoire' }),
  nom: z.string().trim().min(3, 'backoffice.catalogue.residence.nomCourt').max(150),
  adresse: z.string().trim().max(255),
  repere: z.string().trim().max(255),
  description: z.string().trim().max(5000),
  active: z.boolean(),
})
type Saisie = z.infer<typeof schema>

const CHAMPS = ['proprietaire_id', 'quartier_id', 'nom', 'adresse', 'repere', 'description', 'active'] as const

interface Props {
  ouvert: boolean
  residence?: Residence | null
  onFermer: () => void
  onEnregistree: (residence: Residence) => void
}

/** Création ou modification d'une résidence (CdC § 7.1). */
export function FormulaireResidence({ ouvert, residence, onFermer, onEnregistree }: Props) {
  const { t } = useTranslation()
  const [erreur, setErreur] = useState<string | null>(null)

  const proprietaires = useQuery({
    queryKey: ['backoffice', 'proprietaires', 'choix'],
    queryFn: () => lire<{ elements: ProprietaireChoix[] }>('/backoffice/proprietaires', { par_page: 100 }),
    enabled: ouvert,
  })
  const communes = useQuery({
    queryKey: ['referentiels', 'communes'],
    queryFn: () => lire<CommuneChoix[]>('/referentiels/communes'),
    enabled: ouvert,
  })

  const {
    control,
    handleSubmit,
    reset,
    setError,
    watch,
    formState: { isSubmitting },
  } = useForm<Saisie>({
    resolver: zodResolver(schema),
    defaultValues: {
      proprietaire_id: undefined,
      commune_id: undefined,
      quartier_id: undefined,
      nom: '',
      adresse: '',
      repere: '',
      description: '',
      active: true,
    },
  })
  const communeChoisie = watch('commune_id')

  const quartiers = useQuery({
    queryKey: ['referentiels', 'quartiers', communeChoisie],
    queryFn: () => lire<QuartierChoix[]>('/referentiels/quartiers', { commune_id: communeChoisie }),
    enabled: ouvert && communeChoisie !== undefined,
  })

  useEffect(() => {
    if (!ouvert) return
    reset(
      residence
        ? {
            proprietaire_id: residence.proprietaire.id,
            commune_id: residence.lieu.commune_id,
            quartier_id: residence.lieu.quartier_id,
            nom: residence.nom,
            adresse: residence.adresse ?? '',
            repere: residence.repere ?? '',
            description: residence.description ?? '',
            active: residence.active,
          }
        : { proprietaire_id: undefined, commune_id: undefined, quartier_id: undefined, nom: '', adresse: '', repere: '', description: '', active: true },
    )
    setErreur(null)
  }, [ouvert, residence, reset])

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const payload = {
        proprietaire_id: saisie.proprietaire_id,
        quartier_id: saisie.quartier_id,
        nom: saisie.nom,
        adresse: saisie.adresse || undefined,
        repere: saisie.repere || undefined,
        description: saisie.description || undefined,
        active: saisie.active,
      }
      const enregistree = residence ? await modifierLaResidence(residence.id, payload) : await creerUneResidence(payload)
      onEnregistree(enregistree)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={residence ? t('backoffice.catalogue.residence.modifier') : t('backoffice.catalogue.residence.creer')}
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
        <Champ control={control} nom="nom" libelle={t('backoffice.catalogue.residence.nom')} obligatoire />

        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="proprietaire_id"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('backoffice.catalogue.residence.proprietaire')}
                  htmlFor="champ-proprietaire_id"
                  required
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <Select
                    {...field}
                    id="champ-proprietaire_id"
                    size="large"
                    showSearch
                    loading={proprietaires.isPending}
                    optionFilterProp="label"
                    options={proprietaires.data?.elements.map((p) => ({ value: p.id, label: p.nom_affiche }))}
                  />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={6}>
            <Controller
              name="commune_id"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('recherche.filtre.commune')} htmlFor="champ-commune_id">
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
          <Col span={6}>
            <Controller
              name="quartier_id"
              control={control}
              render={({ field, fieldState }) => (
                <Form.Item
                  label={t('recherche.filtre.quartier')}
                  htmlFor="champ-quartier_id"
                  required
                  validateStatus={fieldState.error ? 'error' : undefined}
                  help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
                >
                  <Select
                    {...field}
                    id="champ-quartier_id"
                    size="large"
                    disabled={communeChoisie === undefined}
                    loading={quartiers.isPending}
                    options={quartiers.data?.map((q) => ({ value: q.id, label: q.nom }))}
                  />
                </Form.Item>
              )}
            />
          </Col>
        </Row>

        <Row gutter={12}>
          <Col span={12}>
            <Champ control={control} nom="adresse" libelle={t('backoffice.catalogue.residence.adresse')} />
          </Col>
          <Col span={12}>
            <Champ control={control} nom="repere" libelle={t('backoffice.catalogue.residence.repere')} />
          </Col>
        </Row>

        <Champ control={control} nom="description" libelle={t('backoffice.catalogue.residence.description')} />

        <Controller
          name="active"
          control={control}
          render={({ field }) => (
            <Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)}>
              {t('backoffice.catalogue.residence.active')}
            </Checkbox>
          )}
        />

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
