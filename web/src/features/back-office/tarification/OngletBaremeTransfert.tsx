import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, InputNumber, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi, lire } from '../../../shared/api/client'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { Modal } from '../../../shared/composants/PopupModal'
import { useConfirmerAction } from '../../../shared/composants/confirmer'
import { formaterPrix } from '../../../shared/format/devise'
import type { CommuneChoix, TypeVehiculeChoix } from '../../site-public/types'
import { creerUnBaremeTransfert, listerLesBaremesTransfert, modifierUnBaremeTransfert, supprimerUnBaremeTransfert } from './api'
import type { BaremeTransfert } from './types'

/** Barème des transferts : commune × type de véhicule → prix, réservé administrateur (même patron que le barème de livraison repas). */
export function OngletBaremeTransfert() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [baremeEnEdition, setBaremeEnEdition] = useState<BaremeTransfert | null>(null)

  const baremes = useQuery({ queryKey: ['backoffice', 'tarification', 'baremes-transfert'], queryFn: listerLesBaremesTransfert })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'tarification', 'baremes-transfert'] })

  const supprimer = async (bareme: BaremeTransfert) => {
    const confirme = await confirmer({ titre: t('backoffice.tarification.baremeTransfert.confirmerSuppression'), danger: true })
    if (!confirme) return
    await supprimerUnBaremeTransfert(bareme.id)
    void invalider()
  }

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 16 }}>
        <Button
          type="primary"
          onClick={() => {
            setBaremeEnEdition(null)
            setFormulaireOuvert(true)
          }}
        >
          {t('backoffice.tarification.baremeTransfert.ajouter')}
        </Button>
      </div>

      <Table<BaremeTransfert>
        rowKey="id"
        size="small"
        loading={baremes.isPending}
        dataSource={baremes.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('backoffice.tarification.baremeTransfert.aucun')} /> }}
        columns={[
          { title: t('backoffice.tarification.baremeTransfert.commune'), dataIndex: 'commune', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.tarification.baremeTransfert.typeVehicule'), dataIndex: 'type_vehicule', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.tarification.baremeTransfert.prix'), dataIndex: 'prix', render: (v: number) => formaterPrix(v) },
          {
            title: '',
            key: 'actions',
            render: (_, b) => (
              <Space>
                <Button
                  size="small"
                  onClick={() => {
                    setBaremeEnEdition(b)
                    setFormulaireOuvert(true)
                  }}
                >
                  {t('backoffice.tarification.codesPromo.modifier')}
                </Button>
                <Button size="small" danger onClick={() => void supprimer(b)}>
                  {t('backoffice.tarification.baremeTransfert.supprimer')}
                </Button>
              </Space>
            ),
          },
        ]}
      />

      <Modal
        title={
          baremeEnEdition
            ? t('backoffice.tarification.baremeTransfert.modifier')
            : t('backoffice.tarification.baremeTransfert.ajouter')
        }
        open={formulaireOuvert}
        onCancel={() => setFormulaireOuvert(false)}
        footer={null}
        destroyOnHidden
      >
        <FormulaireBaremeTransfert
          bareme={baremeEnEdition}
          onEnregistre={() => {
            setFormulaireOuvert(false)
            void invalider()
          }}
        />
      </Modal>
    </div>
  )
}

function FormulaireBaremeTransfert({ bareme, onEnregistre }: { bareme: BaremeTransfert | null; onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [communeId, setCommuneId] = useState<number | undefined>(bareme?.commune_id)
  const [typeVehiculeId, setTypeVehiculeId] = useState<number | undefined>(bareme?.type_vehicule_id)
  const [prix, setPrix] = useState<number | null>(bareme?.prix ?? null)
  const [erreur, setErreur] = useState<string | null>(null)

  const communes = useQuery({ queryKey: ['referentiels', 'communes'], queryFn: () => lire<CommuneChoix[]>('/referentiels/communes') })
  const types = useQuery({ queryKey: ['referentiels', 'types-vehicule'], queryFn: () => lire<TypeVehiculeChoix[]>('/referentiels/types-vehicule') })

  const enregistrer = useMutation({
    mutationFn: () =>
      bareme
        ? modifierUnBaremeTransfert(bareme.id, { prix: prix ?? 0 })
        : creerUnBaremeTransfert({ commune_id: communeId!, type_vehicule_id: typeVehiculeId!, prix: prix ?? 0 }),
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.tarification.baremeTransfert.commune')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={communeId}
        onChange={setCommuneId}
        disabled={!!bareme}
        loading={communes.isPending}
        options={communes.data?.map((c) => ({ value: c.id, label: c.nom }))}
      />
      <Typography.Text>{t('backoffice.tarification.baremeTransfert.typeVehicule')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={typeVehiculeId}
        onChange={setTypeVehiculeId}
        disabled={!!bareme}
        loading={types.isPending}
        options={types.data?.map((v) => ({ value: v.id, label: v.nom }))}
      />
      <Typography.Text>{t('backoffice.tarification.baremeTransfert.prix')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={0} precision={0} value={prix} onChange={setPrix} addonAfter="F" />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button
        type="primary"
        loading={enregistrer.isPending}
        disabled={!communeId || !typeVehiculeId || prix === null}
        onClick={() => enregistrer.mutate()}
      >
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
