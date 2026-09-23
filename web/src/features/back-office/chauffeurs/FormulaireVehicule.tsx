import { useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Input, Select, Space, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi, lire } from '../../../shared/api/client'
import type { TypeVehiculeChoix } from '../../site-public/types'
import { creerUnVehicule } from './api'
import type { Vehicule } from './types'

interface Props {
  chauffeurId: number
  onAjoute: (vehicule: Vehicule) => void
}

/** Formulaire simple d'ajout d'un véhicule (CdC § 6.6, Paramètres › Véhicules) : imite le style compact des onglets de tarification. */
export function FormulaireVehicule({ chauffeurId, onAjoute }: Props) {
  const { t } = useTranslation()
  const [typeVehiculeId, setTypeVehiculeId] = useState<number | undefined>(undefined)
  const [immatriculation, setImmatriculation] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const types = useQuery({
    queryKey: ['referentiels', 'types-vehicule'],
    queryFn: () => lire<TypeVehiculeChoix[]>('/referentiels/types-vehicule'),
  })

  const ajouter = useMutation({
    mutationFn: () => creerUnVehicule(chauffeurId, { type_vehicule_id: typeVehiculeId!, immatriculation }),
    onSuccess: (vehicule) => {
      setTypeVehiculeId(undefined)
      setImmatriculation('')
      onAjoute(vehicule)
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text strong>{t('backoffice.chauffeurs.ajouterVehicule')}</Typography.Text>
      <Typography.Text>{t('backoffice.chauffeurs.typeVehicule')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        aria-label={t('backoffice.chauffeurs.typeVehicule')}
        value={typeVehiculeId}
        onChange={setTypeVehiculeId}
        loading={types.isPending}
        options={types.data?.map((v) => ({ value: v.id, label: v.nom }))}
      />
      <Typography.Text>{t('backoffice.chauffeurs.immatriculation')}</Typography.Text>
      <Input
        aria-label={t('backoffice.chauffeurs.immatriculation')}
        value={immatriculation}
        onChange={(e) => setImmatriculation(e.target.value)}
      />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button
        type="primary"
        loading={ajouter.isPending}
        disabled={!typeVehiculeId || !immatriculation.trim()}
        onClick={() => ajouter.mutate()}
      >
        {t('backoffice.chauffeurs.ajouterVehicule')}
      </Button>
    </Space>
  )
}
