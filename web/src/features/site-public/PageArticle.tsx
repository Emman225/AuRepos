import { ArrowLeftOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Alert, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi, lire } from '../../shared/api/client'
import { couleurs } from '../../shared/theme/jetons'
import { policeDisplay } from '../../shared/theme/jetonsSitePublic'
import { BaliseSeo } from './BaliseSeo'
import type { Article } from './types'

/** Fiche d'un article de blog publié (CdC § 12) — ferme le manque documenté : jusqu'ici le blog n'avait aucune page de lecture publique. */
export function PageArticle() {
  const { t } = useTranslation()
  const { slug = '' } = useParams<{ slug: string }>()

  const article = useQuery({
    queryKey: ['blog', slug],
    queryFn: () => lire<Article>(`/blog/${slug}`),
    enabled: !!slug,
  })

  if (article.isPending) {
    return (
      <div style={{ padding: '48px 24px', maxWidth: 760, margin: '0 auto' }}>
        <Skeleton active paragraph={{ rows: 8 }} />
      </div>
    )
  }

  if (article.isError) {
    return (
      <div style={{ padding: '48px 24px', maxWidth: 760, margin: '0 auto' }}>
        <Alert
          type="error"
          showIcon
          title={article.error instanceof ErreurApi && article.error.statut === 404 ? t('blog.introuvable') : t('blog.erreur')}
        />
        <Link to="/blog" style={{ display: 'inline-block', marginTop: 16 }}>
          <ArrowLeftOutlined /> {t('blog.retour')}
        </Link>
      </div>
    )
  }

  const a = article.data

  return (
    <div>
      <BaliseSeo
        titre={`${a.titre} — ${t('marque')}`}
        description={a.resume ?? a.contenu.slice(0, 160)}
        chemin={`/blog/${a.slug}`}
        image={a.image ?? undefined}
      />

      {a.image && (
        <div style={{ width: '100%', maxHeight: 420, overflow: 'hidden' }}>
          <img src={a.image} alt={a.titre} style={{ width: '100%', height: 420, objectFit: 'cover', display: 'block' }} />
        </div>
      )}

      <div style={{ padding: '40px 24px 64px', maxWidth: 760, margin: '0 auto' }}>
        <Link to="/blog" style={{ color: couleurs.texteDiscret, fontSize: 13.5 }}>
          <ArrowLeftOutlined /> {t('blog.retour')}
        </Link>

        {a.publie_le && (
          <Typography.Text style={{ display: 'block', marginTop: 18, color: couleurs.texteDiscret, fontSize: 13 }}>
            {a.publie_le}
          </Typography.Text>
        )}
        <Typography.Title level={1} style={{ fontFamily: policeDisplay, marginTop: 6 }}>
          {a.titre}
        </Typography.Title>
        {a.resume && (
          <Typography.Paragraph style={{ color: couleurs.texteDiscret, fontSize: 17, marginBottom: 28 }}>
            {a.resume}
          </Typography.Paragraph>
        )}

        <div style={{ fontSize: 16, lineHeight: 1.8, color: couleurs.texte }}>
          {a.contenu.split(/\n{2,}/).map((paragraphe, indice) => (
            // eslint-disable-next-line react/no-array-index-key -- contenu figé côté serveur, jamais réordonné
            <Typography.Paragraph key={indice} style={{ whiteSpace: 'pre-wrap' }}>
              {paragraphe}
            </Typography.Paragraph>
          ))}
        </div>
      </div>
    </div>
  )
}
