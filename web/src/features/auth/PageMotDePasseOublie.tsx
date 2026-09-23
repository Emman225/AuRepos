import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Form } from 'antd'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { Champ } from '../../shared/formulaires/Champ'
import { reporterErreurs } from '../../shared/formulaires/reporterErreurs'
import { motDePasseOublie, reinitialiserLeMotDePasse } from './api'
import { CadreAuth } from './CadreAuth'

const schemaCourriel = z.object({
  email: z.string().trim().min(1, 'inscription.courrielObligatoire').email('inscription.courrielInvalide'),
})

const schemaNouveau = z
  .object({
    code: z.string().trim().regex(/^\d{6}$/, 'verification.codeSixChiffres'),
    mot_de_passe: z
      .string()
      .min(8, 'inscription.motDePasseCourt')
      .refine((v) => /[A-Za-zÀ-ÿ]/.test(v) && /[0-9]/.test(v), 'inscription.motDePasseFaible'),
    mot_de_passe_confirmation: z.string(),
  })
  .refine((s) => s.mot_de_passe === s.mot_de_passe_confirmation, {
    path: ['mot_de_passe_confirmation'],
    message: 'inscription.confirmationDifferente',
  })

/**
 * Deux étapes sur la même page : demander le code, puis choisir le nouveau
 * mot de passe. Le serveur répond la même chose que l'adresse existe ou non.
 */
export function PageMotDePasseOublie() {
  const { t } = useTranslation()
  const naviguer = useNavigate()
  const [email, setEmail] = useState<string | null>(null)
  const [refus, setRefus] = useState<string | null>(null)
  const [information, setInformation] = useState<string | null>(null)

  const demande = useForm<z.infer<typeof schemaCourriel>>({ resolver: zodResolver(schemaCourriel), defaultValues: { email: '' } })
  const nouveau = useForm<z.infer<typeof schemaNouveau>>({
    resolver: zodResolver(schemaNouveau),
    defaultValues: { code: '', mot_de_passe: '', mot_de_passe_confirmation: '' },
  })

  const demander = demande.handleSubmit(async (saisie) => {
    setRefus(null)
    try {
      await motDePasseOublie(saisie.email)
      setEmail(saisie.email)
      setInformation(t('motDePasse.codeEnvoye'))
    } catch (e) {
      setRefus(reporterErreurs(e, demande.setError, ['email'], t('connexion.echecInattendu')))
    }
  })

  const changer = nouveau.handleSubmit(async (saisie) => {
    if (!email) return
    setRefus(null)
    try {
      await reinitialiserLeMotDePasse({ email, ...saisie })
      naviguer('/connexion', { replace: true, state: { information: t('motDePasse.change') } })
    } catch (e) {
      setInformation(null)
      setRefus(reporterErreurs(e, nouveau.setError, ['code', 'mot_de_passe'], t('connexion.echecInattendu')))
    }
  })

  return (
    <CadreAuth
      titre={t('motDePasse.titre')}
      sousTitre={email ? t('motDePasse.etape2', { email }) : t('motDePasse.etape1')}
      refus={refus}
      information={information}
      pied={<Link to="/connexion">{t('verification.retourConnexion')}</Link>}
    >
      {!email ? (
        <Form layout="vertical" onFinish={() => void demander()}>
          <Champ control={demande.control} nom="email" libelle={t('inscription.courriel')} obligatoire autoComplete="email" inputMode="email" autoFocus />
          <Button type="primary" htmlType="submit" size="large" block loading={demande.formState.isSubmitting}>
            {t('motDePasse.recevoirLeCode')}
          </Button>
        </Form>
      ) : (
        <Form layout="vertical" onFinish={() => void changer()}>
          <Champ
            control={nouveau.control}
            nom="code"
            libelle={t('verification.code')}
            aide={t('verification.codeAide')}
            obligatoire
            autoComplete="one-time-code"
            inputMode="numeric"
            maxLength={6}
            autoFocus
          />
          <Champ
            control={nouveau.control}
            nom="mot_de_passe"
            libelle={t('motDePasse.nouveau')}
            aide={t('inscription.motDePasseAide')}
            obligatoire
            type="motDePasse"
            autoComplete="new-password"
          />
          <Champ
            control={nouveau.control}
            nom="mot_de_passe_confirmation"
            libelle={t('inscription.confirmation')}
            obligatoire
            type="motDePasse"
            autoComplete="new-password"
          />
          <Button type="primary" htmlType="submit" size="large" block loading={nouveau.formState.isSubmitting}>
            {t('motDePasse.changer')}
          </Button>
        </Form>
      )}
    </CadreAuth>
  )
}
