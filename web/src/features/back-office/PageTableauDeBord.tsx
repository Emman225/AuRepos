import {
  CalendarOutlined,
  FileSearchOutlined,
  LoginOutlined,
  LogoutOutlined,
  ScheduleOutlined,
  TeamOutlined,
  WalletOutlined,
} from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { KPI } from '../../shared/composants/KPI'
import { couleurs } from '../../shared/theme/jetons'
import { useSession } from '../auth/session'
import { compteursDuJour } from './api'

/**
 * Back office › Tableau de bord (CdC § 6 ; brief refonte §6-§7) : un résumé opérationnel
 * hiérarchisé plutôt que des compteurs de même poids — « Aujourd'hui » pour situer la
 * journée, « À traiter » pour ce qui attend une décision, chacun cliquable vers la file
 * déjà filtrée (jamais vers la liste générale).
 */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const compteurs = useQuery({ queryKey: ['backoffice', 'tableau-de-bord'], queryFn: compteursDuJour })

  if (!utilisateur) return null

  return (
    <div>
      <Typography.Title level={2} style={{ marginBottom: 24 }}>
        {t('espace.bienvenue', { nom: utilisateur.prenoms ?? utilisateur.nom })}
      </Typography.Title>

      {compteurs.isPending ? (
        <Skeleton active paragraph={{ rows: 4 }} />
      ) : !compteurs.data ? null : (
        <>
          <Typography.Title level={5} style={{ marginBottom: 12 }}>
            {t('backoffice.tableauDeBord.aujourdhui')}
          </Typography.Title>
          <Row gutter={[16, 16]} style={{ marginBottom: 28 }}>
            <Col xs={24} sm={8}>
              <KPI
                taille="grand"
                icone={<LoginOutlined />}
                libelle={t('backoffice.tableauDeBord.arriveesDuJour')}
                valeur={compteurs.data.arrivees_du_jour}
                lien="/admin/reservations?onglet=arrivees"
              />
            </Col>
            <Col xs={24} sm={8}>
              <KPI
                taille="grand"
                icone={<LogoutOutlined />}
                libelle={t('backoffice.tableauDeBord.departsDuJour')}
                valeur={compteurs.data.departs_du_jour}
                lien="/admin/reservations?onglet=departs"
              />
            </Col>
            <Col xs={24} sm={8}>
              <KPI
                taille="grand"
                icone={<ScheduleOutlined />}
                libelle={t('backoffice.tableauDeBord.sejoursEnCours')}
                valeur={compteurs.data.sejours_en_cours}
                lien="/admin/reservations?onglet=en_cours"
              />
            </Col>
          </Row>

          <Typography.Title level={5} style={{ marginBottom: 12 }}>
            {t('backoffice.tableauDeBord.aTraiter')}
          </Typography.Title>
          <Row gutter={[16, 16]} style={{ marginBottom: 28 }}>
            <Col xs={24} sm={12}>
              <KPI
                icone={<CalendarOutlined />}
                libelle={t('backoffice.tableauDeBord.reservationsEnAttente')}
                valeur={compteurs.data.reservations_en_attente}
                lien="/admin/reservations?onglet=en_attente"
                tonalite={compteurs.data.reservations_en_attente > 0 ? 'alerte' : 'neutre'}
              />
            </Col>
            <Col xs={24} sm={12}>
              <KPI
                icone={<FileSearchOutlined />}
                libelle={t('backoffice.tableauDeBord.devisEnAttente')}
                valeur={compteurs.data.devis_en_attente}
                lien="/admin/reservations?onglet=devis"
                tonalite={compteurs.data.devis_en_attente > 0 ? 'alerte' : 'neutre'}
              />
            </Col>
          </Row>

          <Typography.Title level={5} style={{ marginBottom: 12 }}>
            {t('backoffice.tableauDeBord.accesRapides')}
          </Typography.Title>
          <Row gutter={[16, 16]}>
            <Col xs={12} sm={6}>
              <Link to="/admin/planning" className="kpi-actionnable" style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', border: `1px solid ${couleurs.bordure}`, borderRadius: 10 }}>
                <ScheduleOutlined /> {t('backoffice.menu.planning')}
              </Link>
            </Col>
            <Col xs={12} sm={6}>
              <Link to="/admin/caisse" className="kpi-actionnable" style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', border: `1px solid ${couleurs.bordure}`, borderRadius: 10 }}>
                <WalletOutlined /> {t('backoffice.menu.caisse')}
              </Link>
            </Col>
            <Col xs={12} sm={6}>
              <Link to="/admin/clients" className="kpi-actionnable" style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '10px 14px', border: `1px solid ${couleurs.bordure}`, borderRadius: 10 }}>
                <TeamOutlined /> {t('backoffice.menu.clients')}
              </Link>
            </Col>
          </Row>
        </>
      )}
    </div>
  )
}
