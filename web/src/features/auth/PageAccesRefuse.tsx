import { Button, Result } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { accueilDe } from './espaces'
import { useSession } from './session'

export function PageAccesRefuse() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)

  return (
    <Result
      status="403"
      title="403"
      subTitle={t('erreurs.accesRefuse')}
      extra={
        <Link to={utilisateur ? accueilDe(utilisateur.espace) : '/'}>
          <Button type="primary">{utilisateur ? t('erreurs.retourMonEspace') : t('erreurs.retourAccueil')}</Button>
        </Link>
      }
    />
  )
}
