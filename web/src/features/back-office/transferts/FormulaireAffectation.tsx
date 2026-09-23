import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { Alert, Form, InputNumber, Select } from 'antd'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { z } from 'zod'
import { listerLesChauffeurs, vehiculesDuChauffeur } from '../chauffeurs/api'
import { Modal } from '../../../shared/composants/PopupModal'
import { reporterErreurs } from '../../../shared/formulaires/reporterErreurs'
import { affecterLeTransfert } from './api'
import type { TransfertBackOffice } from './types'

const schema = z.object({
  chauffeur_id: z.number({ error: 'backoffice.transferts.chauffeurObligatoire' }),
  vehicule_id: z.number({ error: 'backoffice.transferts.vehiculeObligatoire' }),
  montant_verse_au_chauffeur: z.number({ error: 'backoffice.transferts.montantObligatoire' }).int().min(0),
})
type Saisie = z.infer<typeof schema>

const CHAMPS = ['chauffeur_id', 'vehicule_id', 'montant_verse_au_chauffeur'] as const

interface Props {
  ouvert: boolean
  transfert: TransfertBackOffice | null
  onFermer: () => void
  onAffecte: (transfert: TransfertBackOffice) => void
}

/** Affectation d'un chauffeur et d'un véhicule à un transfert demandé (CdC § 6.6) : le montant versé au chauffeur est une saisie manuelle, jamais une formule automatique. */
export function FormulaireAffectation({ ouvert, transfert, onFermer, onAffecte }: Props) {
  const { t } = useTranslation()
  const [erreur, setErreur] = useState<string | null>(null)

  const chauffeurs = useQuery({
    queryKey: ['backoffice', 'chauffeurs', 'actifs'],
    queryFn: () => listerLesChauffeurs({ actif: true, par_page: 100 }),
    enabled: ouvert,
  })

  const {
    control,
    handleSubmit,
    reset,
    setError,
    watch,
    formState: { isSubmitting },
  } = useForm<Saisie>({
    resolver: zodResolver(schema),
    defaultValues: { chauffeur_id: undefined, vehicule_id: undefined, montant_verse_au_chauffeur: undefined },
  })
  const chauffeurChoisi = watch('chauffeur_id')

  const vehicules = useQuery({
    queryKey: ['backoffice', 'chauffeurs', chauffeurChoisi, 'vehicules'],
    queryFn: () => vehiculesDuChauffeur(chauffeurChoisi),
    enabled: ouvert && chauffeurChoisi !== undefined,
  })

  useEffect(() => {
    if (!ouvert) return
    reset({ chauffeur_id: undefined, vehicule_id: undefined, montant_verse_au_chauffeur: undefined })
    setErreur(null)
  }, [ouvert, transfert, reset])

  const soumettre = handleSubmit(async (saisie) => {
    if (!transfert) return
    setErreur(null)
    try {
      const affecte = await affecterLeTransfert(transfert.id, saisie)
      onAffecte(affecte)
    } catch (e) {
      setErreur(reporterErreurs(e, setError, CHAMPS, t('tunnel.erreurGenerique')))
    }
  })

  return (
    <Modal
      title={t('backoffice.transferts.affecterLeTransfert')}
      open={ouvert}
      onCancel={() => {
        reset()
        setErreur(null)
        onFermer()
      }}
      onOk={() => void soumettre()}
      confirmLoading={isSubmitting}
      okText={t('backoffice.transferts.affecter')}
      cancelText={t('listes.confirmation.annuler')}
      destroyOnHidden
      width={480}
    >
      <Form layout="vertical">
        <Controller
          name="chauffeur_id"
          control={control}
          render={({ field, fieldState }) => (
            <Form.Item
              label={t('backoffice.transferts.chauffeur')}
              htmlFor="champ-chauffeur_id"
              required
              validateStatus={fieldState.error ? 'error' : undefined}
              help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
            >
              <Select
                {...field}
                id="champ-chauffeur_id"
                size="large"
                showSearch
                loading={chauffeurs.isPending}
                optionFilterProp="label"
                options={chauffeurs.data?.elements.map((c) => ({ value: c.id, label: c.compte.nom_complet }))}
              />
            </Form.Item>
          )}
        />

        <Controller
          name="vehicule_id"
          control={control}
          render={({ field, fieldState }) => (
            <Form.Item
              label={t('backoffice.transferts.vehicule')}
              htmlFor="champ-vehicule_id"
              required
              validateStatus={fieldState.error ? 'error' : undefined}
              help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
            >
              <Select
                {...field}
                id="champ-vehicule_id"
                size="large"
                disabled={chauffeurChoisi === undefined}
                loading={vehicules.isPending}
                options={vehicules.data?.map((v) => ({ value: v.id, label: `${v.immatriculation} — ${v.type_vehicule ?? ''}` }))}
              />
            </Form.Item>
          )}
        />

        <Controller
          name="montant_verse_au_chauffeur"
          control={control}
          render={({ field, fieldState }) => (
            <Form.Item
              label={t('backoffice.transferts.montantVerseAuChauffeur')}
              required
              validateStatus={fieldState.error ? 'error' : undefined}
              help={fieldState.error?.message ? t(fieldState.error.message) : undefined}
            >
              <InputNumber {...field} style={{ width: '100%' }} size="large" min={0} step={100} addonAfter="F" />
            </Form.Item>
          )}
        />

        {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      </Form>
    </Modal>
  )
}
