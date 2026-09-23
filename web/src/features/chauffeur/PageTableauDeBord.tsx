import { CarOutlined, WalletOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { KPI } from '../../shared/composants/KPI'
import { formaterPrix } from '../../shared/format/devise'
import { useSession } from '../auth/session'
import { tableauDeBordDuChauffeur } from './api'

/** Espace chauffeur › Tableau de bord (CdC § 6.6) : transferts affectés en attente, gains — même patron que le tableau de bord apporteur. */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const compteurs = useQuery({ queryKey: ['chauffeur', 'tableau-de-bord'], queryFn: tableauDeBordDuChauffeur })

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
              libelle={t('chauffeur.tableauDeBord.soldeDu')}
              valeur={formaterPrix(compteurs.data.solde_du)}
              tonalite={compteurs.data.solde_du > 0 ? 'succes' : 'neutre'}
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<CarOutlined />}
              libelle={t('chauffeur.tableauDeBord.transfertsAffectes')}
              valeur={compteurs.data.nombre_transferts_affectes}
              lien="/chauffeur/transferts"
              tonalite={compteurs.data.nombre_transferts_affectes > 0 ? 'information' : 'neutre'}
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<WalletOutlined />}
              libelle={t('chauffeur.tableauDeBord.totalGagne')}
              valeur={formaterPrix(compteurs.data.total_gagne)}
              lien="/chauffeur/gains"
            />
          </Col>
        </Row>
      )}
    </div>
  )
}
