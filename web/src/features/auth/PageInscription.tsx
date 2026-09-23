import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Checkbox, Form } from 'antd'
import { useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { z } from 'zod'
import { Champ } from '../../shared/formulaires/Champ'
import { reporterErreurs } from '../../shared/formulaires/reporterErreurs'
import { inscription } from './api'
import { CadreAuth } from './CadreAuth'

// Mêmes règles que le serveur (api/app/Http/Requests/Auth/InscriptionRequest.php).
// Confort de saisie seulement : c'est le serveur qui décide.
const schema = z
  .object({
    nom: z.string().trim().min(1, 'inscription.nomObligatoire').max(100),
    prenoms: z.string().trim().max(150),
    email: z.string().trim().min(1, 'inscription.courrielObligatoire').email('inscription.courrielInvalide'),
    telephone: z
      .string()
      .trim()
      .refine((v) => v === '' || /^\+?[0-9]{8,15}$/.test(v.replace(/[\s.-]/g, '')), 'inscription.telephoneInvalide'),
    mot_de_passe: z
      .string()
      .min(8, 'inscription.motDePasseCourt')
      .refine((v) => /[A-Za-zÀ-ÿ]/.test(v) && /[0-9]/.test(v), 'inscription.motDePasseFaible'),
    mot_de_passe_confirmation: z.string(),
    conditions_acceptees: z.boolean().refine((v) => v, 'inscription.conditionsObligatoires'),
    code_parrain: z.string().trim().max(20),
  })
  .refine((s) => s.mot_de_passe === s.mot_de_passe_confirmation, {
    path: ['mot_de_passe_confirmation'],
    message: 'inscription.confirmationDifferente',
  })
type Saisie = z.infer<typeof schema>

const CHAMPS = ['nom', 'prenoms', 'email', 'telephone', 'mot_de_passe', 'conditions_acceptees', 'code_parrain'] as const

/** Inscription en ligne : crée un compte CLIENT. Les autres profils sont créés par l'entreprise. */
export function PageInscription() {
  const { t } = useTranslation()
  const naviguer = useNavigate()
  const [searchParams] = useSearchParams()
  const [refus, setRefus] = useState<string | null>(null)

  const { control, handleSubmit, setError, formState } = useForm<Saisie>({
    resolver: zodResolver(schema),
    defaultValues: {
      nom: '', prenoms: '', email: '', telephone: '', mot_de_passe: '', mot_de_passe_confirmation: '', conditions_acceptees: false,
      code_parrain: searchParams.get('code') ?? '',
    },
  })

  const soumettre = handleSubmit(async (saisie) => {
    setRefus(null)
    try {
      const { email } = await inscription({ ...saisie, code_parrain: saisie.code_parrain || undefined })
      naviguer('/verification', { state: { email, information: t('verification.codeEnvoye', { email }) } })
    } catch (e) {
      setRefus(reporterErreurs(e, setError, CHAMPS, t('connexion.echecInattendu')))
    }
  })

  return (
    <CadreAuth
      titre={t('inscription.titre')}
      sousTitre={t('inscription.sousTitre')}
      refus={refus}
      pied={
        <>
          {t('inscription.dejaUnCompte')} <Link to="/connexion">{t('connexion.seConnecter')}</Link>
        </>
      }
    >
      <Form layout="vertical" onFinish={() => void soumettre()}>
        <Champ control={control} nom="nom" libelle={t('inscription.nom')} obligatoire autoComplete="family-name" autoFocus />
        <Champ control={control} nom="prenoms" libelle={t('inscription.prenoms')} autoComplete="given-name" />
        <Champ control={control} nom="email" libelle={t('inscription.courriel')} obligatoire autoComplete="email" inputMode="email" />
        <Champ control={control} nom="telephone" libelle={t('inscription.telephone')} aide={t('inscription.telephoneAide')} autoComplete="tel" inputMode="tel" />
        <Champ
          control={control}
          nom="mot_de_passe"
          libelle={t('connexion.motDePasse')}
          aide={t('inscription.motDePasseAide')}
          obligatoire
          type="motDePasse"
          autoComplete="new-password"
        />
        <Champ
          control={control}
          nom="mot_de_passe_confirmation"
          libelle={t('inscription.confirmation')}
          obligatoire
          type="motDePasse"
          autoComplete="new-password"
        />
        <Champ control={control} nom="code_parrain" libelle={t('inscription.codeParrain')} aide={t('inscription.codeParrainAide')} />

        <Controller
          name="conditions_acceptees"
          control={control}
          render={({ field, fieldState }) => (
            <Form.Item
              validateStatus={fieldState.error ? 'error' : undefined}
              help={fieldState.error?.message ? t(fieldState.error.message, { defaultValue: fieldState.error.message }) : undefined}
            >
              <Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)}>
                {t('inscription.jAccepte')} <Link to="/conditions-generales">{t('inscription.conditions')}</Link> *
              </Checkbox>
            </Form.Item>
          )}
        />

        <Button type="primary" htmlType="submit" size="large" block loading={formState.isSubmitting}>
          {t('inscription.creerMonCompte')}
        </Button>
      </Form>
    </CadreAuth>
  )
}
