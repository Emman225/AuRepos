import { LeftOutlined, LockOutlined, RightOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Skeleton, Tooltip, Typography } from 'antd'
import dayjs, { type Dayjs } from 'dayjs'
import { useMemo, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { ErreurApi } from '../../../shared/api/client'
import { useConfirmerAction } from '../../../shared/composants/confirmer'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { couleurs, etatsPlanning } from '../../../shared/theme/jetons'
import { chargerLePlanning, chargerLesBlocages, debloquerDesDates } from './api'
import { LegendePlanning } from './LegendePlanning'
import { ModalBlocageDates } from './ModalBlocageDates'
import { ModalDeplacementSejour } from './ModalDeplacementSejour'
import type { BlocageDuPlanning, LogementDuPlanning, SejourDuPlanning } from './types'

const NB_JOURS = 14
const LARGEUR_COLONNE_LOGEMENT = 200
const LARGEUR_COLONNE_JOUR = 74

/** États de séjour qui autorisent un glisser-déposer — un séjour déjà parti/clôturé ne se déplace plus. */
const ETATS_DEPLACABLES = new Set(['demande', 'confirme', 'arrive'])

/**
 * Table sejour → état visuel du planning (8 états, `etatsPlanning`). Heuristique volontairement
 * simple : `GET /backoffice/planning` n'expose que l'état du séjour (demande/confirme/arrive/…),
 * pas encore les 8 états du planning eux-mêmes (ménage/maintenance/bloqué propriétaire — P2-PLA-01,
 * confirmé absent du modèle par l'audit du 23/09/2026) ni le jour exact de départ dans la grille
 * (une seule barre par séjour, pas une cellule par jour) — seul « départ aujourd'hui » peut se
 * déduire sans deviner une règle métier non écrite.
 */
function etatPlanningDuSejour(s: SejourDuPlanning): keyof typeof etatsPlanning {
  if (dayjs(s.depart).isSame(dayjs(), 'day')) return 'departAujourdhui'
  if (s.etat === 'arrive') return 'occupe'
  return 'reserve'
}

function etatPlanningDuBlocage(motif: string): keyof typeof etatsPlanning {
  switch (motif) {
    case 'maintenance':
      return 'enMaintenance'
    case 'saison_fermee':
      return 'fermee'
    default:
      return 'bloqueProprietaire'
  }
}

interface CibleDeplacement {
  sejour: SejourDuPlanning
  logementSource: LogementDuPlanning
  logementCible: LogementDuPlanning
}

/**
 * Back office › Planning (brief refonte §9, P2-BO-01) : une grille logements × jours, barres
 * temporelles plutôt qu'un tableau — l'écran le plus important du centre de pilotage. Fenêtre
 * fixe de 14 jours, navigable. Glisser-déposer d'un séjour (motif obligatoire, journalisé côté
 * API une fois l'endpoint dédié construit — voir `api.ts::deplacerUnSejour`) et blocage de dates
 * par logement (endpoint réel, déjà utilisé côté fiche logement) s'ajoutent ici à la lecture seule
 * livrée en première passe.
 */
export function PagePlanning() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [debut, setDebut] = useState<Dayjs>(() => dayjs().startOf('day'))
  const [erreur, setErreur] = useState<string | null>(null)

  const [sejourEnCoursDeDeplacement, setSejourEnCoursDeDeplacement] = useState<SejourDuPlanning | null>(null)
  const [ligneSurvolee, setLigneSurvolee] = useState<number | null>(null)
  const [cibleDeplacement, setCibleDeplacement] = useState<CibleDeplacement | null>(null)
  const [logementABloquer, setLogementABloquer] = useState<LogementDuPlanning | null>(null)

  const jours = useMemo(() => Array.from({ length: NB_JOURS }, (_, i) => debut.add(i, 'day')), [debut])
  const du = debut.format('YYYY-MM-DD')
  const au = debut.add(NB_JOURS - 1, 'day').format('YYYY-MM-DD')

  const planning = useQuery({
    queryKey: ['backoffice', 'planning', du, au],
    queryFn: () => chargerLePlanning({ du, au }),
  })

  const idsLogements = (planning.data?.logements ?? []).map((l) => l.id)
  const blocages = useQuery({
    queryKey: ['backoffice', 'planning', 'blocages', idsLogements],
    queryFn: async () => {
      const entrees = await Promise.all(
        (planning.data?.logements ?? []).map(
          async (l) => [l.id, await chargerLesBlocages(l.residence.id, l.id)] as const,
        ),
      )
      return new Map(entrees)
    },
    enabled: idsLogements.length > 0,
  })

  const debloquer = useMutation({
    mutationFn: ({ logement, blocage }: { logement: LogementDuPlanning; blocage: BlocageDuPlanning }) =>
      debloquerDesDates(logement.residence.id, logement.id, blocage.id),
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: ['backoffice', 'planning', 'blocages'] }),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
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

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      <LegendePlanning />

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
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    gap: 6,
                  }}
                >
                  <div style={{ minWidth: 0 }}>
                    <Typography.Text strong style={{ display: 'block', fontSize: 13, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {logement.nom}
                    </Typography.Text>
                    <Typography.Text style={{ display: 'block', fontSize: 11, color: couleurs.texteDiscret, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                      {logement.residence.nom}
                    </Typography.Text>
                  </div>
                  <Tooltip title={t('backoffice.planning.blocage.bouton')}>
                    <Button
                      size="small"
                      type="text"
                      icon={<LockOutlined />}
                      aria-label={t('backoffice.planning.blocage.bouton')}
                      onClick={() => setLogementABloquer(logement)}
                    />
                  </Tooltip>
                </div>

                <div
                  style={{
                    gridColumn: `2 / span ${NB_JOURS}`,
                    display: 'grid',
                    gridTemplateColumns: `repeat(${NB_JOURS}, ${LARGEUR_COLONNE_JOUR}px)`,
                    borderBottom: `1px solid ${couleurs.bordure}`,
                    position: 'relative',
                    minHeight: 46,
                    background: ligneSurvolee === logement.id ? couleurs.sableClair : undefined,
                  }}
                  onDragOver={(e) => {
                    if (!sejourEnCoursDeDeplacement) return
                    e.preventDefault()
                    setLigneSurvolee(logement.id)
                  }}
                  onDragLeave={() => setLigneSurvolee((l) => (l === logement.id ? null : l))}
                  onDrop={(e) => {
                    e.preventDefault()
                    setLigneSurvolee(null)
                    const sejour = sejourEnCoursDeDeplacement
                    setSejourEnCoursDeDeplacement(null)
                    if (!sejour || sejour.logement_id === logement.id) return
                    const logementSource = planning.data!.logements.find((l) => l.id === sejour.logement_id)
                    if (!logementSource) return
                    setCibleDeplacement({ sejour, logementSource, logementCible: logement })
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

                  {(blocages.data?.get(logement.id) ?? []).map((b) => {
                    const debutBlocage = dayjs(b.debut)
                    const finBlocage = dayjs(b.fin)
                    const finFenetre = debut.add(NB_JOURS, 'day')
                    if (finBlocage.isBefore(debut) || debutBlocage.isAfter(finFenetre)) return null
                    const debutClip = debutBlocage.isBefore(debut) ? debut : debutBlocage
                    const finClip = finBlocage.isAfter(finFenetre) ? finFenetre : finBlocage
                    const startIdx = debutClip.diff(debut, 'day')
                    const span = Math.max(1, finClip.diff(debutClip, 'day') + 1)
                    const etat = etatsPlanning[etatPlanningDuBlocage(b.motif)]

                    return (
                      <button
                        key={`blocage-${b.id}`}
                        type="button"
                        title={`${b.motif_libelle}${b.commentaire ? ' — ' + b.commentaire : ''}`}
                        onClick={async () => {
                          const confirme = await confirmer({ titre: t('backoffice.planning.blocage.confirmerDeblocage'), danger: true })
                          if (confirme) debloquer.mutate({ logement, blocage: b })
                        }}
                        style={{
                          position: 'absolute',
                          left: startIdx * LARGEUR_COLONNE_JOUR + 3,
                          width: span * LARGEUR_COLONNE_JOUR - 6,
                          top: 6,
                          bottom: 6,
                          background: `repeating-linear-gradient(45deg, ${etat.fond}, ${etat.fond} 8px, ${etat.fond}CC 8px, ${etat.fond}CC 16px)`,
                          border: 'none',
                          borderRadius: 6,
                          padding: '4px 8px',
                          overflow: 'hidden',
                          cursor: 'pointer',
                          textAlign: 'left',
                        }}
                      >
                        <Typography.Text style={{ display: 'block', fontSize: 12, fontWeight: 600, color: etat.texte, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                          {b.motif_libelle}
                        </Typography.Text>
                      </button>
                    )
                  })}

                  {(sejoursParLogement.get(logement.id) ?? []).map((s) => {
                    const arrivee = dayjs(s.arrivee)
                    const depart = dayjs(s.depart)
                    const debutClip = arrivee.isBefore(debut) ? debut : arrivee
                    const finFenetre = debut.add(NB_JOURS, 'day')
                    const finClip = depart.isAfter(finFenetre) ? finFenetre : depart
                    const startIdx = debutClip.diff(debut, 'day')
                    const span = Math.max(1, finClip.diff(debutClip, 'day'))
                    const etat = etatsPlanning[etatPlanningDuSejour(s)]
                    const deplacable = ETATS_DEPLACABLES.has(s.etat)

                    return (
                      <Link
                        key={s.id}
                        to={`/admin/reservations/${s.id}`}
                        title={`${s.client_nom} — ${s.etat_libelle}`}
                        draggable={deplacable}
                        onDragStart={() => setSejourEnCoursDeDeplacement(s)}
                        onDragEnd={() => {
                          setSejourEnCoursDeDeplacement(null)
                          setLigneSurvolee(null)
                        }}
                        style={{
                          position: 'absolute',
                          left: startIdx * LARGEUR_COLONNE_JOUR + 3,
                          width: span * LARGEUR_COLONNE_JOUR - 6,
                          top: 6,
                          bottom: 6,
                          background: etat.fond,
                          border: `1px solid ${couleurs.bordure}`,
                          borderRadius: 6,
                          padding: '4px 8px',
                          overflow: 'hidden',
                          cursor: deplacable ? 'grab' : 'pointer',
                        }}
                      >
                        <Typography.Text style={{ display: 'block', fontSize: 12, fontWeight: 600, color: etat.texte, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
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

      <ModalDeplacementSejour
        cible={cibleDeplacement}
        onFermer={() => setCibleDeplacement(null)}
        onDeplace={() => {
          setCibleDeplacement(null)
          void queryClient.invalidateQueries({ queryKey: ['backoffice', 'planning'] })
        }}
      />

      <ModalBlocageDates
        logement={logementABloquer}
        onFermer={() => setLogementABloquer(null)}
        onBloque={() => setLogementABloquer(null)}
      />
    </div>
  )
}
