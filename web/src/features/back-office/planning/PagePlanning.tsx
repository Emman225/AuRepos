import { LeftOutlined, RightOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Alert, Button, Skeleton, Typography } from 'antd'
import dayjs, { type Dayjs } from 'dayjs'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { couleurs, tonsSemantiques } from '../../../shared/theme/jetons'
import { tonaliteDuStatut } from '../../../shared/theme/statuts'
import { chargerLePlanning } from './api'
import type { SejourDuPlanning } from './types'

const NB_JOURS = 14
const LARGEUR_COLONNE_LOGEMENT = 200
const LARGEUR_COLONNE_JOUR = 74

/**
 * Back office › Planning (brief refonte §9) : une grille logements × jours, barres temporelles
 * plutôt qu'un tableau — l'écran le plus important du centre de pilotage. Fenêtre fixe de 14
 * jours, navigable, pas de zoom mensuel dans cette première version (P1-BO — la propagation
 * pourra ajouter un zoom semaine/mois une fois cet écran validé).
 */
export function PagePlanning() {
  const { t } = useTranslation()
  const [debut, setDebut] = useState<Dayjs>(() => dayjs().startOf('day'))

  const jours = useMemo(() => Array.from({ length: NB_JOURS }, (_, i) => debut.add(i, 'day')), [debut])
  const du = debut.format('YYYY-MM-DD')
  const au = debut.add(NB_JOURS - 1, 'day').format('YYYY-MM-DD')

  const planning = useQuery({
    queryKey: ['backoffice', 'planning', du, au],
    queryFn: () => chargerLePlanning({ du, au }),
  })

  const sejoursParLogement = useMemo(() => {
    const carte = new Map<number, SejourDuPlanning[]>()
    for (const s of planning.data?.sejours ?? []) {
      const liste = carte.get(s.logement_id) ?? []
      liste.push(s)
      carte.set(s.logement_id, liste)
    }
    return carte
  }, [planning.data])

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.planning')}
        actions={
          <>
            <Button icon={<LeftOutlined />} onClick={() => setDebut((d) => d.subtract(7, 'day'))} />
            <Button onClick={() => setDebut(dayjs().startOf('day'))}>{t('backoffice.planning.aujourdhui')}</Button>
            <Button icon={<RightOutlined />} onClick={() => setDebut((d) => d.add(7, 'day'))} />
          </>
        }
      />

      {planning.isPending ? (
        <Skeleton active paragraph={{ rows: 8 }} />
      ) : planning.isError ? (
        <Alert type="error" showIcon title={t('tunnel.erreurGenerique')} />
      ) : !planning.data || planning.data.logements.length === 0 ? (
        <EtatVide titre={t('backoffice.planning.aucunLogement')} />
      ) : (
        <div style={{ overflowX: 'auto', border: `1px solid ${couleurs.bordure}`, borderRadius: 10 }}>
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: `${LARGEUR_COLONNE_LOGEMENT}px repeat(${NB_JOURS}, ${LARGEUR_COLONNE_JOUR}px)`,
              minWidth: LARGEUR_COLONNE_LOGEMENT + NB_JOURS * LARGEUR_COLONNE_JOUR,
            }}
          >
            {/* En-tête des jours */}
            <div
              style={{
                position: 'sticky',
                left: 0,
                zIndex: 2,
                background: couleurs.blanc,
                borderBottom: `1px solid ${couleurs.bordure}`,
                borderRight: `1px solid ${couleurs.bordure}`,
              }}
            />
            {jours.map((j) => {
              const aujourdhui = j.isSame(dayjs(), 'day')
              const weekend = [0, 6].includes(j.day())
              return (
                <div
                  key={j.format('YYYY-MM-DD')}
                  style={{
                    padding: '10px 6px',
                    textAlign: 'center',
                    background: aujourdhui ? couleurs.sableClair : weekend ? '#FAFAF8' : couleurs.blanc,
                    borderBottom: `1px solid ${couleurs.bordure}`,
                    borderRight: `1px solid ${couleurs.bordure}`,
                  }}
                >
                  <Typography.Text style={{ display: 'block', fontSize: 11, color: couleurs.texteDiscret, textTransform: 'capitalize' }}>
                    {j.format('ddd')}
                  </Typography.Text>
                  <Typography.Text strong={aujourdhui} style={{ fontSize: 13, color: aujourdhui ? couleurs.bleuNuit : couleurs.texte }}>
                    {j.format('D')}
                  </Typography.Text>
                </div>
              )
            })}

            {/* Lignes logements */}
            {planning.data.logements.map((logement) => (
              <div key={logement.id} style={{ display: 'contents' }}>
                <div
                  style={{
                    position: 'sticky',
                    left: 0,
                    zIndex: 1,
                    background: couleurs.blanc,
                    padding: '10px 12px',
                    borderBottom: `1px solid ${couleurs.bordure}`,
                    borderRight: `1px solid ${couleurs.bordure}`,
                    minWidth: 0,
                  }}
                >
                  <Typography.Text strong style={{ display: 'block', fontSize: 13, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {logement.nom}
                  </Typography.Text>
                  <Typography.Text style={{ display: 'block', fontSize: 11, color: couleurs.texteDiscret, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {logement.residence.nom}
                  </Typography.Text>
                </div>

                <div
                  style={{
                    gridColumn: `2 / span ${NB_JOURS}`,
                    display: 'grid',
                    gridTemplateColumns: `repeat(${NB_JOURS}, ${LARGEUR_COLONNE_JOUR}px)`,
                    borderBottom: `1px solid ${couleurs.bordure}`,
                    position: 'relative',
                    minHeight: 46,
                  }}
                >
                  {jours.map((j) => (
                    <div
                      key={j.format('YYYY-MM-DD')}
                      style={{
                        borderRight: `1px solid ${couleurs.bordure}`,
                        background: [0, 6].includes(j.day()) ? '#FAFAF8' : undefined,
                      }}
                    />
                  ))}

                  {(sejoursParLogement.get(logement.id) ?? []).map((s) => {
                    const arrivee = dayjs(s.arrivee)
                    const depart = dayjs(s.depart)
                    const debutClip = arrivee.isBefore(debut) ? debut : arrivee
                    const finFenetre = debut.add(NB_JOURS, 'day')
                    const finClip = depart.isAfter(finFenetre) ? finFenetre : depart
                    const startIdx = debutClip.diff(debut, 'day')
                    const span = Math.max(1, finClip.diff(debutClip, 'day'))
                    const tons = tonsSemantiques[tonaliteDuStatut('sejour', s.etat)]

                    return (
                      <Link
                        key={s.id}
                        to={`/admin/reservations/${s.id}`}
                        title={`${s.client_nom} — ${s.etat_libelle}`}
                        style={{
                          position: 'absolute',
                          left: startIdx * LARGEUR_COLONNE_JOUR + 3,
                          width: span * LARGEUR_COLONNE_JOUR - 6,
                          top: 6,
                          bottom: 6,
                          background: tons.fond,
                          border: `1px solid ${tons.bordure}`,
                          borderRadius: 6,
                          padding: '4px 8px',
                          overflow: 'hidden',
                        }}
                      >
                        <Typography.Text style={{ display: 'block', fontSize: 12, fontWeight: 600, color: tons.texte, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                          {s.client_nom}
                        </Typography.Text>
                      </Link>
                    )
                  })}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
