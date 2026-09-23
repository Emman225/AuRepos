import { CalendarOutlined, HomeOutlined, LoginOutlined, ScheduleOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { KPI } from '../../shared/composants/KPI'
import { useSession } from '../auth/session'
import { compteursDuProprietaire } from './api'

/** Espace propriétaire › Tableau de bord : ses résidences/logements en un coup d'œil, aucun chiffre financier (aucun reversement/commission n'existe encore côté API — pas de donnée inventée). */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const compteurs = useQuery({ queryKey: ['proprietaire', 'tableau-de-bord'], queryFn: compteursDuProprietaire })

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
          <Col xs={24} sm={12} lg={6}>
            <KPI
              taille="grand"
              icone={<HomeOutlined />}
              libelle={t('proprietaire.tableauDeBord.residences')}
              valeur={compteurs.data.nombre_residences}
              lien="/proprietaire/residences"
            />
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <KPI
              taille="grand"
              icone={<ScheduleOutlined />}
              libelle={t('proprietaire.tableauDeBord.logements')}
              valeur={compteurs.data.nombre_logements}
              lien="/proprietaire/residences"
            />
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <KPI
              icone={<CalendarOutlined />}
              libelle={t('proprietaire.tableauDeBord.sejoursEnCours')}
              valeur={compteurs.data.sejours_en_cours}
            />
          </Col>
          <Col xs={24} sm={12} lg={6}>
            <KPI
              icone={<LoginOutlined />}
              libelle={t('proprietaire.tableauDeBord.arriveesSous7Jours')}
              valeur={compteurs.data.arrivees_sous_7_jours}
              tonalite={compteurs.data.arrivees_sous_7_jours > 0 ? 'information' : 'neutre'}
            />
          </Col>
        </Row>
      )}
    </div>
  )
}
