import { zodResolver } from '@hookform/resolvers/zod'
import { Button, Form } from 'antd'
import { useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { ErreurApi } from '../../shared/api/client'
import { Champ } from '../../shared/formulaires/Champ'
import { reporterErreurs } from '../../shared/formulaires/reporterErreurs'
import { renvoyerLeCode, verifierLeCode } from './api'
import { CadreAuth } from './CadreAuth'
import { accueilDe } from './espaces'
import { useSession } from './session'

const schema = z.object({
  email: z.string().trim().min(1, 'inscription.courrielObligatoire').email('inscription.courrielInvalide'),
  code: z.string().trim().regex(/^\d{6}$/, 'verification.codeSixChiffres'),
})
type Saisie = z.infer<typeof schema>

const ATTENTE_AVANT_RENVOI = 60 // secondes — le serveur limite de toute façon à 3 envois par minute

/** Saisie du code à six chiffres reçu par courriel : il active le compte et ouvre la session. */
export function PageVerification() {
  const { t } = useTranslation()
  const naviguer = useNavigate()
  const etat = useLocation().state as { email?: string; information?: string } | null
  const ouvrir = useSession((s) => s.ouvrir)
  const [refus, setRefus] = useState<string | null>(null)
  const [information, setInformation] = useState<string | null>(etat?.information ?? null)
  const [attente, setAttente] = useState(etat?.email ? ATTENTE_AVANT_RENVOI : 0)

  const { control, handleSubmit, setError, getValues, trigger, formState } = useForm<Saisie>({
    resolver: zodResolver(schema),
    defaultValues: { email: etat?.email ?? '', code: '' },
  })

  useEffect(() => {
    if (attente <= 0) return
    const minuterie = setTimeout(() => setAttente((s) => s - 1), 1000)
    return () => clearTimeout(minuterie)
  }, [attente])

  const soumettre = handleSubmit(async ({ email, code }) => {
    setRefus(null)
    try {
      const session = await verifierLeCode(email, code)
      ouvrir(session)
      naviguer(accueilDe(session.utilisateur.espace), { replace: true })
    } catch (e) {
      setInformation(null)
      setRefus(reporterErreurs(e, setError, ['email', 'code'], t('connexion.echecInattendu')))
    }
  })

  const renvoyer = async () => {
    if (!(await trigger('email'))) return
    setRefus(null)
    try {
      await renvoyerLeCode(getValues('email'))
      setInformation(t('verification.nouveauCodeEnvoye'))
      setAttente(ATTENTE_AVANT_RENVOI)
    } catch (e) {
      setInformation(null)
      setRefus(e instanceof ErreurApi ? e.message : t('connexion.echecInattendu'))
    }
  }

  return (
    <CadreAuth
      titre={t('verification.titre')}
      sousTitre={t('verification.sousTitre')}
      refus={refus}
      information={information}
      pied={<Link to="/connexion">{t('verification.retourConnexion')}</Link>}
    >
      <Form layout="vertical" onFinish={() => void soumettre()}>
        <Champ control={control} nom="email" libelle={t('inscription.courriel')} obligatoire autoComplete="email" inputMode="email" />
        <Champ
          control={control}
          nom="code"
          libelle={t('verification.code')}
          aide={t('verification.codeAide')}
          obligatoire
          autoComplete="one-time-code"
          inputMode="numeric"
          maxLength={6}
          autoFocus={Boolean(etat?.email)}
        />
        <Button type="primary" htmlType="submit" size="large" block loading={formState.isSubmitting}>
          {t('verification.valider')}
        </Button>
        <Button type="link" block disabled={attente > 0} onClick={() => void renvoyer()} style={{ marginTop: 8 }}>
          {attente > 0 ? t('verification.renvoyerDans', { secondes: attente }) : t('verification.renvoyer')}
        </Button>
      </Form>
    </CadreAuth>
  )
}
