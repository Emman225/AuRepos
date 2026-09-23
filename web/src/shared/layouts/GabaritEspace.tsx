import { DashboardOutlined, LogoutOutlined, UserOutlined } from '@ant-design/icons'
import { Avatar, Button, Layout, Menu, Space, Tag, Typography } from 'antd'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { deconnexion } from '../../features/auth/api'
import { ACCUEIL_PAR_ESPACE } from '../../features/auth/espaces'
import { useSession } from '../../features/auth/session'
import type { Espace } from '../../features/auth/types'
import { LogoMarque } from '../composants/LogoMarque'
import { SelecteurDeLangue } from '../i18n/SelecteurDeLangue'
import { ECRANS_AGENT_TERRAIN } from './menuAgentTerrain'
import { ECRANS_APPORTEUR } from './menuApporteur'
import { ECRANS_ASSISTANCE } from './menuAssistance'
import { ECRANS_BACKOFFICE } from './menuBackOffice'
import { ECRANS_CHAUFFEUR } from './menuChauffeur'
import { ECRANS_CLIENT } from './menuClient'
import { ECRANS_LIVREUR } from './menuLivreur'
import { ECRANS_PROPRIETAIRE } from './menuProprietaire'
import { ECRANS_RESTAURATEUR } from './menuRestaurateur'
import { couleurs } from '../theme/jetons'

export interface EntreeDeMenu {
  cle: string // adresse de l'écran — c'est elle que le manuel utilisateur citera
  libelle: string
  icone?: ReactNode
}

interface Props {
  titre: string
  espace: Espace
}

/** Gabarit commun à tous les espaces connectés : menu latéral bleu nuit, en-tête avec le compte. */
export function GabaritEspace({ titre, espace }: Props) {
  const { t } = useTranslation()
  const naviguer = useNavigate()
  const lieu = useLocation()
  const utilisateur = useSession((s) => s.utilisateur)

  const racine = ACCUEIL_PAR_ESPACE[espace]
  const menu: EntreeDeMenu[] = utilisateur
    ? [
        { cle: racine, libelle: t('espace.tableauDeBord'), icone: <DashboardOutlined /> },
        ...(espace === 'backoffice'
          ? ECRANS_BACKOFFICE.filter((e) => !e.profils || e.profils.includes(utilisateur.profil)).map((e) => ({
              cle: `${racine}/${e.chemin}`,
              libelle: t(e.cle),
              icone: e.icone,
            }))
          : []),
        ...(espace === 'client'
          ? ECRANS_CLIENT.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
        ...(espace === 'proprietaire'
          ? ECRANS_PROPRIETAIRE.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
        ...(espace === 'apporteur'
          ? ECRANS_APPORTEUR.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
        ...(espace === 'restaurateur'
          ? ECRANS_RESTAURATEUR.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
        ...(espace === 'livreur'
          ? ECRANS_LIVREUR.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
        ...(espace === 'chauffeur'
          ? ECRANS_CHAUFFEUR.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
        ...(espace === 'agent'
          ? ECRANS_AGENT_TERRAIN.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
        ...(espace === 'assistance'
          ? ECRANS_ASSISTANCE.map((e) => ({ cle: `${racine}/${e.chemin}`, libelle: t(e.cle), icone: e.icone }))
          : []),
      ]
    : []

  const sortir = async () => {
    await deconnexion()
    naviguer('/connexion', { replace: true })
  }

  // L'entrée active est la plus longue adresse qui préfixe l'adresse courante.
  const active = [...menu].sort((a, b) => b.cle.length - a.cle.length).find((e) => lieu.pathname.startsWith(e.cle))

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Layout.Sider breakpoint="lg" collapsedWidth={0} width={248}>
        <div style={{ padding: '20px 24px' }}>
          <LogoMarque taille={30} surFondSombre variante="icone" />
          <Typography.Text style={{ color: couleurs.sable, fontSize: 13, display: 'block', marginTop: 10 }}>{titre}</Typography.Text>
        </div>
        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={active ? [active.cle] : []}
          onClick={({ key }) => naviguer(key)}
          items={menu.map((e) => ({ key: e.cle, label: e.libelle, icon: e.icone }))}
        />
      </Layout.Sider>

      <Layout>
        <Layout.Header
          style={{
            background: couleurs.blanc,
            borderBottom: `1px solid ${couleurs.bordure}`,
            display: 'flex',
            justifyContent: 'flex-end',
            alignItems: 'center',
            padding: '0 24px',
            lineHeight: 'normal', // l'en-tête Ant Design impose 64px par ligne, ce qui rognait le nom du compte
          }}
        >
          {utilisateur && (
            <Space size="middle">
              {/* fr / en réservé au site public et à l'espace client (CdC § 13.2) */}
              {espace === 'client' && <SelecteurDeLangue />}
              <Avatar icon={<UserOutlined />} style={{ background: couleurs.sable, color: couleurs.bleuNuit }} />
              <span style={{ lineHeight: 1.2 }}>
                <Typography.Text strong style={{ display: 'block' }}>
                  {utilisateur.nom_complet}
                </Typography.Text>
                <Tag color={couleurs.bleuNuit} style={{ marginInlineEnd: 0 }}>
                  {utilisateur.profil_libelle}
                </Tag>
              </span>
              <Button icon={<LogoutOutlined />} onClick={() => void sortir()}>
                {t('connexion.seDeconnecter')}
              </Button>
            </Space>
          )}
        </Layout.Header>
        <Layout.Content style={{ padding: 24 }}>
          <Outlet />
        </Layout.Content>
      </Layout>
    </Layout>
  )
}
