import { CarOutlined, CheckCircleOutlined, WalletOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { KPI } from '../../shared/composants/KPI'
import { formaterPrix } from '../../shared/format/devise'
import { useSession } from '../auth/session'
import { compteursDuLivreur } from './api'

/** Espace livreur › Tableau de bord : courses affectées en attente, gains (CdC — espace livreur). */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const compteurs = useQuery({ queryKey: ['livreur', 'tableau-de-bord'], queryFn: compteursDuLivreur })

  if (!utilisateur) return null

  return (
    <div>
      <Typography.Title level={2} style={{ marginBottom: 24 }}>
        {t('espace.bienvenue', { nom: utilisateur.prenoms ?? utilisateur.nom })}
      </Typography.Title>

      {compteurs.isPending ? (
        <Skeleton active paragraph={{ rows: 4 }} />
      ) : !compteurs.data ? null : (
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={8}>
            <KPI
              taille="grand"
              icone={<WalletOutlined />}
              libelle={t('livreur.tableauDeBord.soldeDu')}
              valeur={formaterPrix(compteurs.data.gains.solde_du)}
              tonalite={compteurs.data.gains.solde_du > 0 ? 'succes' : 'neutre'}
              lien="/livreur/gains"
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<CarOutlined />}
              libelle={t('livreur.tableauDeBord.coursesEnCours')}
              valeur={compteurs.data.courses_en_cours}
              lien="/livreur/courses"
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<CheckCircleOutlined />}
              libelle={t('livreur.tableauDeBord.coursesLivrees')}
              valeur={compteurs.data.courses_livrees}
              lien="/livreur/courses"
            />
          </Col>
        </Row>
      )}
    </div>
  )
}
