import { zodResolver } from '@hookform/resolvers/zod'
import { LockOutlined, UserOutlined } from '@ant-design/icons'
import { Button, Form } from 'antd'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { ErreurApi } from '../../shared/api/client'
import { Champ } from '../../shared/formulaires/Champ'
import { connexion } from './api'
import { CadreAuth } from './CadreAuth'
import { accueilDe } from './espaces'
import { useSession } from './session'

const schema = z.object({
  identifiant: z.string().trim().min(1, 'connexion.identifiantObligatoire'),
  motDePasse: z.string().min(1, 'connexion.motDePasseObligatoire'),
})
type Saisie = z.infer<typeof schema>

/** Une adresse de retour n'est suivie que si elle appartient à l'espace du compte. */
function destination(retour: unknown, accueil: string): string {
  return typeof retour === 'string' && retour.startsWith(accueil) ? retour : accueil
}

/** LA page de connexion, commune aux douze profils : le profil du compte décide de la suite. */
export function PageConnexion() {
  const { t } = useTranslation()
  const naviguer = useNavigate()
  const lieu = useLocation()
  const { statut, utilisateur, ouvrir } = useSession()
  const [refus, setRefus] = useState<string | null>(null)
  const etat = lieu.state as { retour?: string; information?: string } | null

  const { control, handleSubmit, formState } = useForm<Saisie>({
    resolver: zodResolver(schema),
    defaultValues: { identifiant: '', motDePasse: '' },
  })

  // Déjà connecté : inutile de montrer le formulaire.
  if (statut === 'connecte' && utilisateur) {
    return <Navigate to={accueilDe(utilisateur.espace)} replace />
  }

  const soumettre = handleSubmit(async ({ identifiant, motDePasse }) => {
    setRefus(null)
    try {
      const session = await connexion(identifiant, motDePasse)
      ouvrir(session)
      naviguer(destination(etat?.retour, accueilDe(session.utilisateur.espace)), { replace: true })
    } catch (e) {
      // Inscription commencée mais code jamais saisi : on y emmène le client.
      if (e instanceof ErreurApi && e.champs?.code?.[0] === 'courriel_non_verifie') {
        naviguer('/verification', { state: { email: identifiant.trim(), information: e.message } })
        return
      }
      setRefus(e instanceof ErreurApi ? e.message : t('connexion.echecInattendu'))
    }
  })

  return (
    <CadreAuth
      titre={t('connexion.titre')}
      sousTitre={t('connexion.sousTitre')}
      refus={refus}
      information={etat?.information}
      pied={
        <>
          {t('connexion.pasDeCompte')} <Link to="/inscription">{t('connexion.creerUnCompte')}</Link>
        </>
      }
    >
      <Form layout="vertical" onFinish={() => void soumettre()}>
        <Champ
          control={control}
          nom="identifiant"
          libelle={t('connexion.identifiant')}
          aide={t('connexion.identifiantAide')}
          obligatoire
          prefixe={<UserOutlined />}
          autoComplete="username"
          autoFocus
        />
        <Champ
          control={control}
          nom="motDePasse"
          libelle={t('connexion.motDePasse')}
          obligatoire
          type="motDePasse"
          prefixe={<LockOutlined />}
          autoComplete="current-password"
        />
        <div style={{ textAlign: 'right', marginTop: -12, marginBottom: 16 }}>
          <Link to="/mot-de-passe-oublie">{t('connexion.motDePasseOublie')}</Link>
        </div>
        <Button type="primary" htmlType="submit" size="large" block loading={formState.isSubmitting}>
          {t('connexion.seConnecter')}
        </Button>
      </Form>
    </CadreAuth>
  )
}
