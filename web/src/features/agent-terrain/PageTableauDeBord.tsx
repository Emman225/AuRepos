import { LoginOutlined, LogoutOutlined, ToolOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { KPI } from '../../shared/composants/KPI'
import { useSession } from '../auth/session'
import { mesMissions, mesSejoursDuJour } from './api'

/**
 * Espace agent de terrain › Tableau de bord (CdC § 6.3, P2-MOB-04) : ma journée — arrivées à
 * accueillir, départs à faire partir, missions de ménage à faire — même patron que les tableaux
 * de bord chauffeur/restaurateur. Aucun point d'entrée d'agrégation dédié côté API
 * (routes/api_v1/agent.php n'expose qu'un tableau de bord PAR SÉJOUR/mission, pas un résumé) :
 * les compteurs sont donc dérivés ici des deux listes déjà nécessaires aux écrans « Mes séjours »
 * et « Mes missions ».
 */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const sejours = useQuery({ queryKey: ['agent', 'sejours'], queryFn: mesSejoursDuJour })
  const missions = useQuery({ queryKey: ['agent', 'missions', {}], queryFn: () => mesMissions() })

  if (!utilisateur) return null

  const chargement = sejours.isPending || missions.isPending
  const arrivees = sejours.data?.filter((s) => s.etat === 'confirme').length ?? 0
  const departs = sejours.data?.filter((s) => s.etat === 'arrive').length ?? 0
  const missionsAFaire = missions.data?.filter((m) => m.statut !== 'faite').length ?? 0

  return (
    <div>
      <Typography.Title level={2} style={{ marginBottom: 24 }}>
        {t('espace.bienvenue', { nom: utilisateur.prenoms ?? utilisateur.nom })}
      </Typography.Title>

      {chargement ? (
        <Skeleton active paragraph={{ rows: 4 }} />
      ) : (
        <Row gutter={[16, 16]}>
          <Col xs={24} sm={8}>
            <KPI
              taille="grand"
              icone={<LoginOutlined />}
              libelle={t('agent.tableauDeBord.arriveesAAccueillir')}
              valeur={arrivees}
              lien="/agent/sejours"
              tonalite={arrivees > 0 ? 'information' : 'neutre'}
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<LogoutOutlined />}
              libelle={t('agent.tableauDeBord.departsAFaireSortir')}
              valeur={departs}
              lien="/agent/sejours"
              tonalite={departs > 0 ? 'information' : 'neutre'}
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<ToolOutlined />}
              libelle={t('agent.tableauDeBord.missionsAFaire')}
              valeur={missionsAFaire}
              lien="/agent/missions"
              tonalite={missionsAFaire > 0 ? 'alerte' : 'neutre'}
            />
          </Col>
        </Row>
      )}
    </div>
  )
}
