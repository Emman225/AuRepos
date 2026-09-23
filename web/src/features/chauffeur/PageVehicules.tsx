import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Col, Input, Row, Select, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi, lire } from '../../shared/api/client'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import type { TypeVehiculeChoix } from '../site-public/types'
import { ajouterUnVehicule, mesVehicules } from './api'
import type { VehiculeChauffeur } from './types'

/** Espace chauffeur › Mes véhicules (CdC § 6.6, Paramètres) : SES véhicules, en lecture/écriture. */
export function PageVehicules() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [typeVehiculeId, setTypeVehiculeId] = useState<number | undefined>(undefined)
  const [immatriculation, setImmatriculation] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const vehicules = useQuery({ queryKey: ['chauffeur', 'vehicules'], queryFn: mesVehicules })
  const types = useQuery({ queryKey: ['referentiels', 'types-vehicule'], queryFn: () => lire<TypeVehiculeChoix[]>('/referentiels/types-vehicule') })

  const ajouter = useMutation({
    mutationFn: () => ajouterUnVehicule({ type_vehicule_id: typeVehiculeId!, immatriculation }),
    onSuccess: () => {
      setTypeVehiculeId(undefined)
      setImmatriculation('')
      setErreur(null)
      void queryClient.invalidateQueries({ queryKey: ['chauffeur', 'vehicules'] })
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      <EnTeteDePage titre={t('chauffeur.menu.vehicules')} />

      <Table<VehiculeChauffeur>
        rowKey="id"
        loading={vehicules.isPending}
        dataSource={vehicules.data}
        pagination={false}
        style={{ marginBottom: 24 }}
        locale={{ emptyText: <EtatVide titre={t('chauffeur.vehicules.aucun')} /> }}
        columns={[
          { title: t('chauffeur.vehicules.immatriculation'), dataIndex: 'immatriculation' },
          { title: t('chauffeur.vehicules.typeVehicule'), dataIndex: 'type_vehicule', render: (v: string | null) => v ?? '—' },
          {
            title: t('chauffeur.vehicules.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => (
              <StatutBadge domaine="actif" code={v ? 'actif' : 'inactif'} libelle={v ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')} />
            ),
          },
        ]}
      />

      <Typography.Title level={5}>{t('chauffeur.vehicules.ajouter')}</Typography.Title>
      <Row gutter={12} align="bottom">
        <Col xs={24} sm={10}>
          <Typography.Text style={{ display: 'block', marginBottom: 4 }}>{t('chauffeur.vehicules.typeVehicule')}</Typography.Text>
          <Select
            style={{ width: '100%' }}
            aria-label={t('chauffeur.vehicules.typeVehicule')}
            value={typeVehiculeId}
            onChange={setTypeVehiculeId}
            loading={types.isPending}
            options={types.data?.map((v) => ({ value: v.id, label: v.nom }))}
          />
        </Col>
        <Col xs={24} sm={10}>
          <Typography.Text style={{ display: 'block', marginBottom: 4 }}>{t('chauffeur.vehicules.immatriculation')}</Typography.Text>
          <Input
            aria-label={t('chauffeur.vehicules.immatriculation')}
            value={immatriculation}
            onChange={(e) => setImmatriculation(e.target.value)}
          />
        </Col>
        <Col xs={24} sm={4}>
          <Button
            type="primary"
            block
            loading={ajouter.isPending}
            disabled={!typeVehiculeId || !immatriculation.trim()}
            onClick={() => ajouter.mutate()}
          >
            {t('chauffeur.vehicules.ajouter')}
          </Button>
        </Col>
      </Row>
      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
    </div>
  )
}
