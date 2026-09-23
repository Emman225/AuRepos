import { ShoppingCartOutlined, ToolOutlined, WalletOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Col, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { KPI } from '../../shared/composants/KPI'
import { formaterPrix } from '../../shared/format/devise'
import { useSession } from '../auth/session'
import { compteursDuRestaurateur } from './api'

/** Espace restaurateur › Tableau de bord : commandes par état, dette (CdC — espace restaurateur). */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const compteurs = useQuery({ queryKey: ['restaurateur', 'tableau-de-bord'], queryFn: compteursDuRestaurateur })

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
              libelle={t('restaurateur.tableauDeBord.dette')}
              valeur={formaterPrix(compteurs.data.dette.du)}
              tonalite={compteurs.data.dette.du > 0 ? 'succes' : 'neutre'}
              lien="/restaurateur/dette"
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<ToolOutlined />}
              libelle={t('restaurateur.tableauDeBord.aPreparer')}
              valeur={compteurs.data.commandes_par_etat.confirmee ?? 0}
              lien="/restaurateur/commandes"
            />
          </Col>
          <Col xs={24} sm={8}>
            <KPI
              icone={<ShoppingCartOutlined />}
              libelle={t('restaurateur.tableauDeBord.enAttente')}
              valeur={compteurs.data.commandes_par_etat.demande ?? 0}
              lien="/restaurateur/commandes"
            />
          </Col>
        </Row>
      )}
    </div>
  )
}
