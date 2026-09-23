import { useQuery } from '@tanstack/react-query'
import { Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { lire } from '../../shared/api/client'
import { couleurs } from '../../shared/theme/jetons'
import { laitonClair, policeDisplay } from '../../shared/theme/jetonsSitePublic'
import { BaliseSeo } from './BaliseSeo'

type CleTexteLegal = 'conditions.cgv' | 'conditions.confidentialite'

const CHEMIN_PAR_CLE: Record<CleTexteLegal, string> = {
  'conditions.cgv': '/conditions-generales',
  'conditions.confidentialite': '/confidentialite',
}

interface Configuration {
  'conditions.cgv': string | null
  'conditions.confidentialite': string | null
}

interface Props {
  cle: CleTexteLegal
  titre: string
}

/** CGV et politique de confidentialité (CdC § 12, onglet « Termes et conditions ») : même gabarit, texte saisi côté Paramètres. */
export function PageLegale({ cle, titre }: Props) {
  const { t } = useTranslation()
  const configuration = useQuery({ queryKey: ['configuration'], queryFn: () => lire<Configuration>('/configuration') })
  const texte = configuration.data?.[cle]

  return (
    <div style={{ padding: '56px 24px 80px', maxWidth: 720, margin: '0 auto', width: '100%' }}>
      <BaliseSeo titre={`${titre} — ${t('marque')}`} description={titre} chemin={CHEMIN_PAR_CLE[cle]} />
      <Typography.Title level={1} style={{ fontFamily: policeDisplay, fontWeight: 500, marginBottom: 16 }}>
        {titre}
      </Typography.Title>
      <div style={{ width: 48, height: 3, background: laitonClair, borderRadius: 2, marginBottom: 36 }} />
      {configuration.isPending && <Skeleton active />}
      {configuration.data &&
        (texte ? (
          <Typography.Paragraph
            style={{ whiteSpace: 'pre-wrap', fontSize: 15.5, lineHeight: 1.8, color: couleurs.texte }}
          >
            {texte}
          </Typography.Paragraph>
        ) : (
          <Typography.Paragraph type="secondary" style={{ fontSize: 15 }}>
            {t('legal.pasEncoreDisponible')}
          </Typography.Paragraph>
        ))}
    </div>
  )
}
