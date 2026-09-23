import { useQuery } from '@tanstack/react-query'
import { Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { lire } from '../../shared/api/client'
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
    <div style={{ padding: '48px 24px', maxWidth: 800, margin: '0 auto', width: '100%' }}>
      <BaliseSeo titre={`${titre} — ${t('marque')}`} description={titre} chemin={CHEMIN_PAR_CLE[cle]} />
      <Typography.Title level={1}>{titre}</Typography.Title>
      {configuration.isPending && <Skeleton active />}
      {configuration.data &&
        (texte ? (
          <Typography.Paragraph style={{ whiteSpace: 'pre-wrap' }}>{texte}</Typography.Paragraph>
        ) : (
          <Typography.Paragraph type="secondary">{t('legal.pasEncoreDisponible')}</Typography.Paragraph>
        ))}
    </div>
  )
}
