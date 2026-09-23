import { useQuery } from '@tanstack/react-query'
import { Col, Empty, Pagination, Row, Skeleton, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link, useSearchParams } from 'react-router-dom'
import { lire } from '../../shared/api/client'
import { couleurs } from '../../shared/theme/jetons'
import { policeDisplay } from '../../shared/theme/jetonsSitePublic'
import { BaliseSeo } from './BaliseSeo'
import type { ResultatsBlog } from './types'

const PAR_PAGE = 9

/** Vignette d'article, même langage visuel que les cartes de logement (photo, dégradé, texte en surimpression). */
function CarteArticle({ article }: { article: ResultatsBlog['elements'][number] }) {
  return (
    <Link to={`/blog/${article.slug}`}>
      <div
        style={{
          position: 'relative',
          borderRadius: 10,
          overflow: 'hidden',
          aspectRatio: '4 / 3',
          boxShadow: '0 10px 26px rgba(21, 43, 71, 0.14)',
          marginBottom: 14,
        }}
      >
        {article.image ? (
          <img
            src={article.image}
            alt={article.titre}
            loading="lazy"
            style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }}
          />
        ) : (
          <div style={{ position: 'absolute', inset: 0, background: couleurs.sableClair }} />
        )}
        <div
          style={{
            position: 'absolute',
            inset: 0,
            background: 'linear-gradient(0deg, rgba(21,43,71,0.85) 0%, rgba(21,43,71,0.25) 45%, rgba(21,43,71,0) 65%)',
          }}
        />
        {article.publie_le && (
          <Typography.Text style={{ position: 'absolute', left: 16, top: 14, color: 'rgba(255,255,255,0.85)', fontSize: 12 }}>
            {article.publie_le}
          </Typography.Text>
        )}
        <Typography.Text strong style={{ position: 'absolute', left: 16, right: 16, bottom: 14, color: couleurs.blanc, fontSize: 16 }}>
          {article.titre}
        </Typography.Text>
      </div>
      {article.resume && (
        <Typography.Paragraph style={{ color: couleurs.texteDiscret, fontSize: 13.5, marginBottom: 0 }} ellipsis={{ rows: 2 }}>
          {article.resume}
        </Typography.Paragraph>
      )}
    </Link>
  )
}

/** Blog public (CdC § 12) : seuls les articles publiés apparaissent ici. */
export function PageBlog() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const page = Number(searchParams.get('page') ?? '1')

  const articles = useQuery({
    queryKey: ['blog', page],
    queryFn: () => lire<ResultatsBlog>('/blog', { page, par_page: PAR_PAGE }),
  })

  return (
    <div style={{ padding: '48px 24px', maxWidth: 1120, margin: '0 auto', width: '100%' }}>
      <BaliseSeo titre={`${t('blog.titre')} — ${t('marque')}`} description={t('blog.sousTitre')} chemin="/blog" />

      <Typography.Title level={1} style={{ fontFamily: policeDisplay }}>
        {t('blog.titre')}
      </Typography.Title>
      <Typography.Paragraph style={{ color: couleurs.texteDiscret, fontSize: 15, marginBottom: 36, maxWidth: 620 }}>
        {t('blog.sousTitre')}
      </Typography.Paragraph>

      {articles.isPending ? (
        <Row gutter={[24, 24]}>
          {[1, 2, 3].map((n) => (
            <Col key={n} xs={24} sm={12} md={8}>
              <Skeleton.Image active style={{ width: '100%', aspectRatio: '4 / 3', height: 'auto' }} />
            </Col>
          ))}
        </Row>
      ) : articles.data && articles.data.elements.length > 0 ? (
        <>
          <Row gutter={[24, 32]}>
            {articles.data.elements.map((article) => (
              <Col key={article.slug} xs={24} sm={12} md={8}>
                <CarteArticle article={article} />
              </Col>
            ))}
          </Row>
          {articles.data.pagination.derniere_page > 1 && (
            <div style={{ display: 'flex', justifyContent: 'center', marginTop: 40 }}>
              <Pagination
                current={articles.data.pagination.page}
                pageSize={PAR_PAGE}
                total={articles.data.pagination.total}
                showSizeChanger={false}
                onChange={(p) => setSearchParams((prev) => { prev.set('page', String(p)); return prev })}
              />
            </div>
          )}
        </>
      ) : (
        <Empty description={t('blog.aucun')} />
      )}
    </div>
  )
}
