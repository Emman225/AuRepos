import { ClockCircleOutlined } from '@ant-design/icons'
import { Descriptions } from 'antd'
import { useTranslation } from 'react-i18next'
import { useSession } from '../features/auth/session'
import { EnTeteDePage } from '../shared/composants/EnTeteDePage'
import { EtatVide } from '../shared/composants/EtatVide'
import { couleurs } from '../shared/theme/jetons'

/** Accueil provisoire de chaque espace, en attendant ses vrais écrans (P1-BO-01, P3-WEB-01…). */
export function TableauDeBordProvisoire() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  if (!utilisateur) return null

  return (
    <>
      <EnTeteDePage titre={t('espace.bienvenue', { nom: utilisateur.prenoms ?? utilisateur.nom })} />

      <EtatVide
        icone={<ClockCircleOutlined />}
        titre={t('espace.bientotDisponible')}
        description={t('espace.bientotDisponibleDetail')}
      />

      <div style={{ maxWidth: 480, marginTop: 24, border: `1px solid ${couleurs.bordure}`, borderRadius: 10, padding: '4px 18px' }}>
        <Descriptions column={1} size="small" title={t('espace.monCompte')}>
          <Descriptions.Item label={t('espace.profil')}>{utilisateur.profil_libelle}</Descriptions.Item>
          <Descriptions.Item label={t('espace.courriel')}>{utilisateur.email}</Descriptions.Item>
          <Descriptions.Item label={t('espace.agence')}>{utilisateur.agence?.nom ?? t('espace.aucuneAgence')}</Descriptions.Item>
          <Descriptions.Item label={t('espace.encaissement')}>
            {utilisateur.peut_encaisser ? t('espace.peutEncaisser') : t('espace.nePeutPasEncaisser')}
          </Descriptions.Item>
        </Descriptions>
      </div>
    </>
  )
}
