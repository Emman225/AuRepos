import { TeamOutlined, TransactionOutlined, WalletOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { KPI } from '../../shared/composants/KPI'
import { formaterPrix } from '../../shared/format/devise'
import { useSession } from '../auth/session'
import { compteursDeLApporteur } from './api'

/** Espace apporteur › Tableau de bord : filleuls, commissions, solde dû (le reversement lui-même reste initié par l'agence, via la caisse). */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const compteurs = useQuery({ queryKey: ['apporteur', 'tableau-de-bord'], queryFn: compteursDeLApporteur })

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
              libelle={t('apporteur.tableauDeBord.soldeDu')}
              valeur={formaterPrix(compteurs.data.solde_du)}
              tonalite={compteurs.data.solde_du > 0 ? 'succes' : 'neutre'}
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<TeamOutlined />}
              libelle={t('apporteur.tableauDeBord.filleuls')}
              valeur={compteurs.data.nombre_filleuls}
              lien="/apporteur/filleuls"
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<TransactionOutlined />}
              libelle={t('apporteur.tableauDeBord.commissions')}
              valeur={compteurs.data.nombre_commissions}
              lien="/apporteur/commissions"
            />
          </Col>
        </Row>
      )}
    </div>
  )
}
