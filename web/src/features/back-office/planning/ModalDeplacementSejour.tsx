import { useMutation } from '@tanstack/react-query'
import { Alert, Form, Input } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { deplacerUnSejour } from './api'
import type { LogementDuPlanning, SejourDuPlanning } from './types'

interface Props {
  /** null = modale fermée. */
  cible: { sejour: SejourDuPlanning; logementSource: LogementDuPlanning; logementCible: LogementDuPlanning } | null
  onFermer: () => void
  onDeplace: () => void
}

const LONGUEUR_MIN_MOTIF = 5

/**
 * Glisser-déposer d'un séjour vers un autre logement (P2-BO-01/P2-PLA-02) : le motif est
 * obligatoire (déplacement journalisé côté API, CdC), demandé via une modale réutilisant
 * `PopupModal` — jamais `window.prompt` (brief § 6.8, même règle que `useConfirmerAction`).
 *
 * Contrainte « même type de logement uniquement » (P2-PLA-02) volontairement NON vérifiée ici :
 * `GET /backoffice/planning` n'expose pas encore le type du logement (confirmé en lisant
 * `PlanningLogementResource.php`), donc rien à comparer côté client sans deviner une donnée
 * absente. Un avertissement le rappelle dans la modale plutôt que de fabriquer un contrôle
 * silencieusement faux.
 */
export function ModalDeplacementSejour({ cible, onFermer, onDeplace }: Props) {
  const { t } = useTranslation()
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (cible) {
      setMotif('')
      setErreur(null)
    }
  }, [cible])

  const deplacer = useMutation({
    mutationFn: () => deplacerUnSejour(cible!.sejour.id, cible!.logementCible.id, motif),
    onSuccess: onDeplace,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const bloque = motif.trim().length < LONGUEUR_MIN_MOTIF

  return (
    <Modal
      title={t('backoffice.planning.deplacer.titre')}
      open={cible !== null}
      onCancel={onFermer}
      onOk={() => deplacer.mutate()}
      confirmLoading={deplacer.isPending}
      okText={t('listes.confirmation.confirmer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: bloque }}
      destroyOnHidden
      width={440}
    >
      {cible && (
        <>
          <Alert
            type="info"
            showIcon
            style={{ marginBottom: 12 }}
            title={t('backoffice.planning.deplacer.resume', {
              client: cible.sejour.client_nom,
              source: cible.logementSource.nom,
              cible: cible.logementCible.nom,
            })}
          />
          <Alert type="warning" showIcon style={{ marginBottom: 12 }} title={t('backoffice.planning.deplacer.avertissementType')} />
          <Form layout="vertical">
            <Form.Item label={t('backoffice.planning.deplacer.motif')} htmlFor="champ-motif-deplacement" required>
              <Input.TextArea
                id="champ-motif-deplacement"
                rows={3}
                value={motif}
                onChange={(e) => setMotif(e.target.value)}
                aria-label={t('backoffice.planning.deplacer.motif')}
              />
            </Form.Item>
          </Form>
          {erreur && <Alert type="error" showIcon title={erreur} />}
        </>
      )}
    </Modal>
  )
}
