import { useQuery } from '@tanstack/react-query'
import { Button, Layout, Space } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link, Outlet } from 'react-router-dom'
import { accueilDe } from '../../features/auth/espaces'
import { useSession } from '../../features/auth/session'
import { BandeauCookies } from '../../features/site-public/BandeauCookies'
import { BoutonWhatsApp } from '../../features/site-public/BoutonWhatsApp'
import { FormulaireNewsletter } from '../../features/site-public/FormulaireNewsletter'
import { lire } from '../api/client'
import { LogoMarque } from '../composants/LogoMarque'
import { SelecteurDeLangue } from '../i18n/SelecteurDeLangue'
import { couleurs } from '../theme/jetons'

interface Configuration {
  'general.whatsapp': string | null
}

/** Gabarit commun aux pages du site public (CdC § 5.1, 5.2) : en-tête, langue, pied de page. */
export function GabaritSitePublic() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const configuration = useQuery({ queryKey: ['configuration'], queryFn: () => lire<Configuration>('/configuration') })

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Layout.Header
        style={{
          height: 'auto',
          lineHeight: 'normal',
          display: 'flex',
          flexWrap: 'wrap',
          rowGap: 10,
          alignItems: 'center',
          justifyContent: 'space-between',
          padding: '14px 24px',
        }}
      >
        <Link to="/" style={{ display: 'flex', alignItems: 'center' }}>
          <LogoMarque taille={34} surFondSombre variante="icone" />
        </Link>
        <nav>
          <Space size="large">
            <Link to="/recherche" className="lien-entete" style={{ color: 'rgba(255,255,255,0.82)' }}>
              {t('entete.logements')}
            </Link>
            <Link to="/blog" className="lien-entete" style={{ color: 'rgba(255,255,255,0.82)' }}>
              {t('entete.blog')}
            </Link>
          </Space>
        </nav>
        <Space size="middle" wrap>
          <SelecteurDeLangue />
          <Link to={utilisateur ? accueilDe(utilisateur.espace) : '/connexion'}>
            <Button style={{ background: couleurs.sable, borderColor: couleurs.sable, color: couleurs.bleuNuit }}>
              {utilisateur ? t(`espaces.${utilisateur.espace}`) : t('connexion.seConnecter')}
            </Button>
          </Link>
        </Space>
      </Layout.Header>

      <Layout.Content>
        <Outlet />
      </Layout.Content>

      <Layout.Footer style={{ textAlign: 'center', color: couleurs.texteDiscret }}>
        <div style={{ marginBottom: 16, display: 'flex', justifyContent: 'center' }}>
          <FormulaireNewsletter />
        </div>
        <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 12 }}>
          <LogoMarque taille={72} />
        </div>
        <Space size="middle" style={{ marginBottom: 8 }}>
          <Link to="/conditions-generales" style={{ color: couleurs.texteDiscret }}>
            {t('legal.cgv')}
          </Link>
          <Link to="/confidentialite" style={{ color: couleurs.texteDiscret }}>
            {t('legal.confidentialite')}
          </Link>
        </Space>
        <div>
          © {new Date().getFullYear()} {t('marque')}
        </div>
      </Layout.Footer>

      <BoutonWhatsApp numero={configuration.data?.['general.whatsapp'] ?? null} />
      <BandeauCookies />
    </Layout>
  )
}
