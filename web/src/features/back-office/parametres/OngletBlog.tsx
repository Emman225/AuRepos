import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Select, Space, Table, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { usePagination } from '../../../shared/composants/usePagination'
import { creerUnArticle, listerLesArticles, modifierUnArticle } from './api'
import type { Article, SaisieArticle, StatutArticle } from './types'

const COULEUR_STATUT: Record<StatutArticle, string> = { brouillon: 'default', publie: 'green' }

/** Paramètres › Divers › Blog (CdC § 12, P1-BO-10). */
export function OngletBlog() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [statut, setStatut] = useState<StatutArticle | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [nouvelArticleOuvert, setNouvelArticleOuvert] = useState(false)
  const [articleOuvert, setArticleOuvert] = useState<Article | null>(null)

  const filtres = { recherche: recherche || undefined, statut, page, par_page: parPage }
  const articles = useQuery({ queryKey: ['backoffice', 'articles', filtres], queryFn: () => listerLesArticles(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'articles'] })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Space wrap>
          <Input.Search
            placeholder={t('backoffice.parametres.blog.rechercher')}
            value={recherche}
            onChange={(e) => {
              setRecherche(e.target.value)
              reinitialiser()
            }}
            style={{ width: 260 }}
            allowClear
          />
          <Select
            style={{ width: 180 }}
            allowClear
            placeholder={t('backoffice.parametres.blog.statut')}
            value={statut}
            onChange={(v) => {
              setStatut(v)
              reinitialiser()
            }}
            options={[
              { value: 'brouillon', label: t('backoffice.parametres.blog.statuts.brouillon') },
              { value: 'publie', label: t('backoffice.parametres.blog.statuts.publie') },
            ]}
          />
        </Space>
        <Space>
          <BoutonsExport url="/backoffice/articles/export" filtres={{ recherche: recherche || undefined, statut }} nomFichier="Articles" />
          <Button type="primary" onClick={() => setNouvelArticleOuvert(true)}>
            {t('backoffice.parametres.blog.nouveau')}
          </Button>
        </Space>
      </div>

      <Table<Article>
        rowKey="id"
        size="small"
        loading={articles.isPending}
        dataSource={articles.data?.elements}
        pagination={propsPagination(articles.data?.pagination.total)}
        onRow={(a) => ({ onClick: () => setArticleOuvert(a), style: { cursor: 'pointer' } })}
        columns={[
          { title: t('backoffice.parametres.blog.titre'), dataIndex: 'titre' },
          { title: t('backoffice.parametres.blog.auteur'), key: 'auteur', render: (_, a) => a.auteur?.nom ?? '—' },
          {
            title: t('backoffice.parametres.blog.statut'),
            dataIndex: 'statut',
            render: (v: StatutArticle, a) => <Tag color={COULEUR_STATUT[v]}>{a.statut_libelle}</Tag>,
          },
          { title: t('backoffice.parametres.blog.publieLe'), dataIndex: 'publie_le', render: (v: string | null) => v ?? '—' },
        ]}
      />

      <Modal title={t('backoffice.parametres.blog.nouveau')} open={nouvelArticleOuvert} onCancel={() => setNouvelArticleOuvert(false)} footer={null} destroyOnHidden width={640}>
        <FormulaireArticle article={null} onEnregistre={() => { setNouvelArticleOuvert(false); void invalider() }} />
      </Modal>

      <Modal title={articleOuvert?.titre} open={articleOuvert !== null} onCancel={() => setArticleOuvert(null)} footer={null} destroyOnHidden width={640}>
        {articleOuvert && (
          <FormulaireArticle article={articleOuvert} onEnregistre={() => { setArticleOuvert(null); void invalider() }} />
        )}
      </Modal>
    </div>
  )
}

function FormulaireArticle({ article, onEnregistre }: { article: Article | null; onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [titre, setTitre] = useState(article?.titre ?? '')
  const [resume, setResume] = useState(article?.resume ?? '')
  const [contenu, setContenu] = useState(article?.contenu ?? '')
  const [imageUrl, setImageUrl] = useState(article?.image_url ?? '')
  const [statut, setStatut] = useState<StatutArticle>(article?.statut ?? 'brouillon')
  const [erreur, setErreur] = useState<string | null>(null)

  const enregistrer = useMutation({
    mutationFn: () => {
      const saisie: SaisieArticle = {
        titre, resume: resume || undefined, contenu, image_url: imageUrl || undefined, statut,
      }
      return article ? modifierUnArticle(article.id, saisie) : creerUnArticle(saisie)
    },
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.parametres.blog.titre')}</Typography.Text>
      <Input value={titre} onChange={(e) => setTitre(e.target.value)} />
      <Typography.Text>{t('backoffice.parametres.blog.resume')}</Typography.Text>
      <Input value={resume} onChange={(e) => setResume(e.target.value)} />
      <Typography.Text>{t('backoffice.parametres.blog.image')}</Typography.Text>
      <Input value={imageUrl} onChange={(e) => setImageUrl(e.target.value)} placeholder="https://…" />
      <Typography.Text>{t('backoffice.parametres.blog.contenu')}</Typography.Text>
      <Input.TextArea rows={8} value={contenu} onChange={(e) => setContenu(e.target.value)} />
      <Typography.Text>{t('backoffice.parametres.blog.statut')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={statut}
        onChange={setStatut}
        options={[
          { value: 'brouillon', label: t('backoffice.parametres.blog.statuts.brouillon') },
          { value: 'publie', label: t('backoffice.parametres.blog.statuts.publie') },
        ]}
      />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} disabled={!titre || !contenu} onClick={() => enregistrer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
