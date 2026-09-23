import { FilterOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Col, Drawer, Empty, Pagination, Row, Skeleton, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useSearchParams } from 'react-router-dom'
import { ErreurApi, lire } from '../../shared/api/client'
import { couleurs } from '../../shared/theme/jetons'
import { BaliseSeo } from './BaliseSeo'
import { CarteLogement } from './CarteLogement'
import { FormulaireRecherche } from './FormulaireRecherche'
import type { CriteresRecherche, ResultatsRecherche } from './types'

function filtresDepuisUrl(params: URLSearchParams): CriteresRecherche {
  const nombre = (cle: string): number | undefined => {
    const v = params.get(cle)
    return v ? Number(v) : undefined
  }

  return {
    commune_id: nombre('commune_id'),
    quartier_id: nombre('quartier_id'),
    arrivee: params.get('arrivee') ?? undefined,
    depart: params.get('depart') ?? undefined,
    adultes: nombre('adultes'),
    enfants: nombre('enfants'),
    type_logement_id: nombre('type_logement_id'),
    budget_max: nombre('budget_max'),
    equipements: params.getAll('equipements').map(Number),
    page: nombre('page'),
  }
}

function urlDepuisFiltres(filtres: CriteresRecherche): URLSearchParams {
  const params = new URLSearchParams()
  const ajouter = (cle: string, valeur: string | number | undefined): void => {
    if (valeur !== undefined && valeur !== '') params.set(cle, String(valeur))
  }

  ajouter('commune_id', filtres.commune_id)
  ajouter('quartier_id', filtres.quartier_id)
  ajouter('arrivee', filtres.arrivee)
  ajouter('depart', filtres.depart)
  ajouter('adultes', filtres.adultes)
  ajouter('enfants', filtres.enfants)
  ajouter('type_logement_id', filtres.type_logement_id)
  ajouter('budget_max', filtres.budget_max)
  ajouter('page', filtres.page)
  for (const id of filtres.equipements ?? []) params.append('equipements', String(id))

  return params
}

/**
 * Résultats de recherche (brief refonte §15) : filtres en colonne fixe à gauche sur desktop
 * (jamais un bandeau horizontal qui repousse les résultats), tiroir sur mobile — les résultats
 * eux-mêmes restent des vignettes photo, prix dominant (CdC § 5.1, 5.2).
 */
export function PageRecherche() {
  const { t } = useTranslation()
  const [searchParams, setSearchParams] = useSearchParams()
  const filtres = filtresDepuisUrl(searchParams)
  const [filtresOuverts, setFiltresOuverts] = useState(false)

  const resultats = useQuery({
    queryKey: ['recherche', searchParams.toString()],
    queryFn: () =>
      lire<ResultatsRecherche>('/catalogue/recherche', {
        ...filtres,
        equipements: filtres.equipements?.length ? filtres.equipements : undefined,
      }),
  })

  const rechercher = (nouveauxFiltres: CriteresRecherche): void => {
    setSearchParams(urlDepuisFiltres(nouveauxFiltres))
    setFiltresOuverts(false)
  }

  const changerDePage = (page: number): void => {
    setSearchParams(urlDepuisFiltres({ ...filtres, page }))
  }

  return (
    <div style={{ padding: '48px 24px', maxWidth: 1280, margin: '0 auto', width: '100%' }}>
      <BaliseSeo titre={`${t('recherche.titre')} — ${t('marque')}`} description={t('recherche.description')} chemin="/recherche" />

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', gap: 16, flexWrap: 'wrap' }}>
        <Typography.Title level={1} style={{ marginBottom: 4 }}>
          {t('recherche.titre')}
        </Typography.Title>
        <Button className="recherche-bouton-filtres" icon={<FilterOutlined />} onClick={() => setFiltresOuverts(true)}>
          {t('recherche.filtre.ouvrirLesFiltres')}
        </Button>
      </div>

      {resultats.data && (
        <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, marginBottom: 24 }}>
          {t(resultats.data.avec_dates ? 'recherche.resultats.pourLesDates' : 'recherche.resultats.disponibles', {
            count: resultats.data.pagination.total,
          })}
        </Typography.Text>
      )}

      <div className="recherche-disposition">
        <aside className="recherche-panneau-filtres">
          <FormulaireRecherche filtresInitiaux={filtres} onRechercher={rechercher} />
        </aside>

        <Drawer
          title={t('recherche.filtre.ouvrirLesFiltres')}
          placement="bottom"
          size="88%"
          open={filtresOuverts}
          onClose={() => setFiltresOuverts(false)}
        >
          <FormulaireRecherche filtresInitiaux={filtres} onRechercher={rechercher} />
        </Drawer>

        <div>
          {resultats.isPending && <Skeleton active />}
          {resultats.isError && (
            <Alert
              type="error"
              showIcon
              title={resultats.error instanceof ErreurApi ? resultats.error.message : t('recherche.resultats.erreur')}
            />
          )}
          {resultats.data && (
            <>
              {resultats.data.elements.length === 0 ? (
                <Empty description={t('recherche.resultats.aucun')} />
              ) : (
                <Row gutter={[16, 16]}>
                  {resultats.data.elements.map((logement) => (
                    <Col key={logement.reference} xs={24} sm={12} xl={8}>
                      <CarteLogement logement={logement} />
                    </Col>
                  ))}
                </Row>
              )}

              {resultats.data.pagination.derniere_page > 1 && (
                <Pagination
                  style={{ marginTop: 24, textAlign: 'center' }}
                  current={resultats.data.pagination.page}
                  pageSize={resultats.data.pagination.par_page}
                  total={resultats.data.pagination.total}
                  onChange={changerDePage}
                  showSizeChanger={false}
                />
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}
