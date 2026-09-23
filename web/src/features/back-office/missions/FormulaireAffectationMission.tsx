import { useMutation } from '@tanstack/react-query'
import { Alert, Form, InputNumber, Typography } from 'antd'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { affecterUnAgentALaMission } from './api'
import type { Mission } from './types'

interface Props {
  mission: Mission | null
  onFermer: () => void
  onAffectee: () => void
}

/**
 * Affectation à un agent de terrain (CdC § 6.4). L'API (`MissionResource`) ne renvoie jamais
 * l'identifiant de l'agent déjà affecté, et aucun point d'entrée back office ne liste les comptes
 * « agent_terrain » (PersonnelController exclut ce profil de sa requête, pas seulement de son
 * filtre) : en l'absence d'un tel annuaire, la saisie reste l'identifiant numérique du compte,
 * à obtenir auprès du service concerné en attendant un écran de rattachement dédié.
 */
export function FormulaireAffectationMission({ mission, onFermer, onAffectee }: Props) {
  const { t } = useTranslation()
  const [agentId, setAgentId] = useState<number | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  useEffect(() => {
    if (mission) {
      setAgentId(null)
      setErreur(null)
    }
  }, [mission])

  const affecter = useMutation({
    mutationFn: () => affecterUnAgentALaMission(mission!.id, agentId!),
    onSuccess: onAffectee,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Modal
      title={t('backoffice.missions.affecter.titre')}
      open={mission !== null}
      onCancel={onFermer}
      onOk={() => affecter.mutate()}
      confirmLoading={affecter.isPending}
      okText={t('backoffice.missions.affecter.action')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: !agentId }}
      destroyOnHidden
      width={420}
    >
      <Typography.Paragraph type="secondary">{t('backoffice.missions.affecter.aide')}</Typography.Paragraph>
      <Form layout="vertical">
        <Form.Item label={t('backoffice.missions.affecter.agentId')} htmlFor="champ-agent-id">
          <InputNumber
            id="champ-agent-id"
            style={{ width: '100%' }}
            size="large"
            min={1}
            value={agentId}
            onChange={setAgentId}
            aria-label={t('backoffice.missions.affecter.agentId')}
          />
        </Form.Item>
      </Form>
      {erreur && <Alert style={{ marginTop: 8 }} type="error" showIcon title={erreur} />}
    </Modal>
  )
}
