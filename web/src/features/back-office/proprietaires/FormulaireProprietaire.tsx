import { zodResolver } from '@hookform/resolvers/zod'
import { Alert, Checkbox, Col, DatePicker, Form, InputNumber, Row, Select, Typography } from 'antd'
import dayjs from 'dayjs'
import { useEffect, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { Modal } from '../../../shared/composants/PopupModal'
import { Champ } from '../../../shared/formulaires/Champ'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { useSession } from '../../auth/session'
import { creerUnProprietaire, modifierLeProprietaire } from './api'
import type { Proprietaire } from './types'

const schema = z.object({
  nom: z.string().trim().min(1, 'backoffice.proprietaires.nomObligatoire').max(100),
  prenoms: z.string().trim().max(150),
  email: z.string().trim().min(1, 'backoffice.proprietaires.courrielObligatoire').email('inscription.courrielInvalide'),
  telephone: z.string().trim().max(30),
  nature: z.enum(['personne_physique', 'entreprise']),
  raison_sociale: z.string().trim().max(255),
  regime_fiscal: z.enum(['non_renseigne', 'aucun', 'entreprenant', 'micro_entreprise', 'reel_simplifie', 'reel_normal']),
  assujetti_tva: z.boolean(),
  ncc: z.string().trim().max(30),
  rccm: z.string().trim().max(60),
  adresse: z.string().trim().max(255),
  mode_remuneration: z.enum(['prix_negocie', 'commission']),
  taux_commission: z.number().min(0).max(100).nullable(),
  mandat_signe_le: z.custom<dayjs.Dayjs>().nullable(),
  notes: z.string().trim().max(3000),
  interne: z.boolean(),
})
type Saisie = z.infer<typeof schema>

const CHAMPS = ['nom', 'prenoms', 'email', 'telephone', 'nature', 'raison_sociale', 'regime_fiscal', 'ncc', 'rccm', 'adresse', 'mode_remuneration', 'taux_commission', 'mandat_signe_le', 'notes'] as const

interface Props {
  ouvert: boolean
  proprietaire?: Proprietaire | null
  onFermer: () => void
  onEnregistre: (proprietaire: Proprietaire) => void
}

/** Création ou modification d'un propriétaire (CdC § 7.2) : fiche, régime fiscal, mandat. */
export function FormulaireProprietaire({ ouvert, proprietaire, onFermer, onEnregistre }: Props) {
  const { t } = useTranslation()
  const estAdministrateur = useSession((s) => s.utilisateur?.profil === 'administrateur' || s.utilisateur?.profil === 'super_administrateur')
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
      nom: '', prenoms: '', email: '', telephone: '', nature: 'personne_physique', raison_sociale: '',
      regime_fiscal: 'non_renseigne', assujetti_tva: false, ncc: '', rccm: '', adresse: '',
      mode_remuneration: 'prix_negocie', taux_commission: null, mandat_signe_le: null, notes: '', interne: false,
    },
  })
  const nature = useWatch({ control, name: 'nature' })
  const assujettiTva = useWatch({ control, name: 'assujetti_tva' })
  const modeRemuneration = useWatch({ control, name: 'mode_remuneration' })

  useEffect(() => {
    if (!ouvert) return
    reset(
      proprietaire
        ? {
            nom: proprietaire.compte.nom, prenoms: proprietaire.compte.prenoms ?? '', email: proprietaire.compte.email, telephone: proprietaire.compte.telephone ?? '',
            nature: proprietaire.nature, raison_sociale: proprietaire.raison_sociale ?? '', regime_fiscal: proprietaire.regime_fiscal, assujetti_tva: proprietaire.assujetti_tva,
            ncc: proprietaire.ncc ?? '', rccm: proprietaire.rccm ?? '', adresse: proprietaire.adresse ?? '',
            mode_remuneration: proprietaire.mandat.mode_remuneration, taux_commission: proprietaire.mandat.taux_commission,
            mandat_signe_le: proprietaire.mandat.signe_le ? dayjs(proprietaire.mandat.signe_le, 'DD/MM/YYYY') : null,
            notes: proprietaire.notes ?? '', interne: proprietaire.interne,
          }
        : {
            nom: '', prenoms: '', email: '', telephone: '', nature: 'personne_physique', raison_sociale: '',
            regime_fiscal: 'non_renseigne', assujetti_tva: false, ncc: '', rccm: '', adresse: '',
            mode_remuneration: 'prix_negocie', taux_commission: null, mandat_signe_le: null, notes: '', interne: false,
          },
    )
    setErreur(null)
  }, [ouvert, proprietaire, reset])

  const soumettre = handleSubmit(async (saisie) => {
    setErreur(null)
    try {
      const payload = {
        nom: saisie.nom,
        prenoms: saisie.prenoms || undefined,
        email: saisie.email,
        telephone: saisie.telephone || undefined,
        nature: saisie.nature,
        raison_sociale: saisie.raison_sociale || undefined,
        regime_fiscal: saisie.regime_fiscal,
        assujetti_tva: saisie.assujetti_tva,
        ncc: saisie.ncc || undefined,
        rccm: saisie.rccm || undefined,
        adresse: saisie.adresse || undefined,
        mode_remuneration: saisie.mode_remuneration,
        taux_commission: saisie.taux_commission ?? undefined,
        mandat_signe_le: saisie.mandat_signe_le ? saisie.mandat_signe_le.format('YYYY-MM-DD') : undefined,
        notes: saisie.notes || undefined,
        // La clé « interne » elle-même est réservée aux administrateurs (403 si un gestionnaire
        // l'envoie, quelle que soit sa valeur) : on ne l'inclut donc jamais pour les autres profils.
        ...(estAdministrateur ? { interne: saisie.interne } : {}),
      }
      const enregistre = proprietaire ? await modifierLeProprietaire(proprietaire.id, payload) : await creerUnProprietaire(payload)
      onEnregistre(enregistre)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={proprietaire ? t('backoffice.proprietaires.modifier') : t('backoffice.proprietaires.creer')}
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
      width={640}
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
              name="nature"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('backoffice.proprietaires.nature')} htmlFor="champ-nature">
                  <Select
                    {...field}
                    id="champ-nature"
                    size="large"
                    options={[
                      { value: 'personne_physique', label: t('backoffice.proprietaires.naturePersonnePhysique') },
                      { value: 'entreprise', label: t('backoffice.proprietaires.natureEntreprise') },
                    ]}
                  />
                </Form.Item>
              )}
            />
          </Col>
          {nature === 'entreprise' && (
            <Col span={12}>
              <Champ control={control} nom="raison_sociale" libelle={t('backoffice.proprietaires.raisonSociale')} obligatoire />
            </Col>
          )}
        </Row>

        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="regime_fiscal"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('backoffice.proprietaires.regimeFiscal')} htmlFor="champ-regime_fiscal">
                  <Select
                    {...field}
                    id="champ-regime_fiscal"
                    size="large"
                    options={[
                      { value: 'non_renseigne', label: t('backoffice.proprietaires.regime.nonRenseigne') },
                      { value: 'aucun', label: t('backoffice.proprietaires.regime.aucun') },
                      { value: 'entreprenant', label: t('backoffice.proprietaires.regime.entreprenant') },
                      { value: 'micro_entreprise', label: t('backoffice.proprietaires.regime.microEntreprise') },
                      { value: 'reel_simplifie', label: t('backoffice.proprietaires.regime.reelSimplifie') },
                      { value: 'reel_normal', label: t('backoffice.proprietaires.regime.reelNormal') },
                    ]}
                  />
                </Form.Item>
              )}
            />
          </Col>
          <Col span={12} style={{ display: 'flex', alignItems: 'center', gap: 16, marginTop: 30 }}>
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
        </Row>

        <Row gutter={12}>
          {assujettiTva && (
            <Col span={12}>
              <Champ control={control} nom="ncc" libelle={t('backoffice.proprietaires.ncc')} obligatoire />
            </Col>
          )}
          <Col span={12}>
            <Champ control={control} nom="rccm" libelle={t('backoffice.proprietaires.rccm')} />
          </Col>
        </Row>

        <Champ control={control} nom="adresse" libelle={t('backoffice.catalogue.residence.adresse')} />

        <Typography.Title level={5}>{t('backoffice.proprietaires.mandat.titre')}</Typography.Title>
        <Row gutter={12}>
          <Col span={12}>
            <Controller
              name="mode_remuneration"
              control={control}
              render={({ field }) => (
                <Form.Item label={t('backoffice.proprietaires.mandat.modeRemuneration')} htmlFor="champ-mode_remuneration">
                  <Select
                    {...field}
                    id="champ-mode_remuneration"
                    size="large"
                    options={[
                      { value: 'prix_negocie', label: t('backoffice.proprietaires.mandat.prixNegocie') },
                      { value: 'commission', label: t('backoffice.proprietaires.mandat.commission') },
                    ]}
                  />
                </Form.Item>
              )}
            />
          </Col>
          {modeRemuneration === 'commission' && (
            <Col span={12}>
              <Controller
                name="taux_commission"
                control={control}
                render={({ field, fieldState }) => (
                  <Form.Item
                    label={t('backoffice.proprietaires.mandat.tauxCommission')}
                    required
                    validateStatus={fieldState.error ? 'error' : undefined}
                  >
                    <InputNumber {...field} style={{ width: '100%' }} size="large" min={0} max={100} />
                  </Form.Item>
                )}
              />
            </Col>
          )}
        </Row>
        <Controller
          name="mandat_signe_le"
          control={control}
          render={({ field }) => (
            <Form.Item label={t('backoffice.proprietaires.mandat.signeLe')}>
              <DatePicker style={{ width: '100%' }} size="large" format="DD/MM/YYYY" value={field.value} onChange={field.onChange} />
            </Form.Item>
          )}
        />

        <Champ control={control} nom="notes" libelle={t('backoffice.proprietaires.notes')} />

        {estAdministrateur && (
          <>
            <Controller
              name="interne"
              control={control}
              render={({ field }) => (
                <Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)}>
                  {t('backoffice.proprietaires.interne')}
                </Checkbox>
              )}
            />
            <Typography.Paragraph type="secondary" style={{ marginTop: 4 }}>
              {t('backoffice.proprietaires.interneAide')}
            </Typography.Paragraph>
          </>
        )}

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
