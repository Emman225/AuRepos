import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Alert, DatePicker, Form, Input, Select } from 'antd'
import dayjs, { type Dayjs } from 'dayjs'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { bloquerDesDates } from './api'
import type { LogementDuPlanning } from './types'

interface Props {
  /** null = modale fermée. */
  logement: LogementDuPlanning | null
  onFermer: () => void
  onBloque: () => void
}

/** Les 3 motifs saisissables à la main — `canal_externe` est posé par la synchronisation des canaux, jamais ici (CalendrierController::bloquer). */
const MOTIFS = ['maintenance', 'usage_proprietaire', 'saison_fermee'] as const

/**
 * Blocage de dates d'un logement (P2-BO-01) : « Occupée/Disponible » côté propriétaire, réutilise
 * l'endpoint RÉEL et déjà en production de la fiche logement
 * (`POST /backoffice/residences/{residence}/logements/{logement}/blocages`,
 * `CalendrierController::bloquer`) — pas un contrat inventé pour l'occasion.
 */
export function ModalBlocageDates({ logement, onFermer, onBloque }: Props) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [periode, setPeriode] = useState<[Dayjs, Dayjs] | null>(null)
  const [motif, setMotif] = useState<(typeof MOTIFS)[number] | undefined>(undefined)
  const [commentaire, setCommentaire] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (logement) {
      setPeriode(null)
      setMotif(undefined)
      setCommentaire('')
      setErreur(null)
    }
  }, [logement])

  const bloquer = useMutation({
    mutationFn: () =>
      bloquerDesDates(logement!.residence.id, logement!.id, {
        debut: periode![0].format('YYYY-MM-DD'),
        fin: periode![1].format('YYYY-MM-DD'),
        motif: motif!,
        commentaire: commentaire || undefined,
      }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: ['backoffice', 'planning', 'blocages'] })
      onBloque()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const bloque = !periode || !motif

  return (
    <Modal
      title={t('backoffice.planning.blocage.titre', { logement: logement?.nom })}
      open={logement !== null}
      onCancel={onFermer}
      onOk={() => bloquer.mutate()}
      confirmLoading={bloquer.isPending}
      okText={t('backoffice.planning.blocage.bouton')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: bloque }}
      destroyOnHidden
      width={440}
    >
      <Form layout="vertical">
        <Form.Item label={t('backoffice.planning.blocage.periode')} required>
          <DatePicker.RangePicker
            style={{ width: '100%' }}
            format="DD/MM/YYYY"
            value={periode}
            disabledDate={(d) => d.isBefore(dayjs(), 'day')}
            onChange={(v) => setPeriode(v && v[0] && v[1] ? [v[0], v[1]] : null)}
          />
        </Form.Item>
        <Form.Item label={t('backoffice.planning.blocage.motif')} htmlFor="champ-motif-blocage" required>
          <Select
            id="champ-motif-blocage"
            value={motif}
            onChange={setMotif}
            aria-label={t('backoffice.planning.blocage.motif')}
            options={MOTIFS.map((m) => ({ value: m, label: t(`backoffice.planning.blocage.motifs.${m}`) }))}
          />
        </Form.Item>
        <Form.Item label={t('backoffice.planning.blocage.commentaire')} htmlFor="champ-commentaire-blocage">
          <Input.TextArea
            id="champ-commentaire-blocage"
            rows={2}
            value={commentaire}
            onChange={(e) => setCommentaire(e.target.value)}
            aria-label={t('backoffice.planning.blocage.commentaire')}
          />
        </Form.Item>
      </Form>
      {erreur && <Alert type="error" showIcon title={erreur} />}
    </Modal>
  )
}
