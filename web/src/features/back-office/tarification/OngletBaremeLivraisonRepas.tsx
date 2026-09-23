import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, InputNumber, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { Modal } from '../../../shared/composants/PopupModal'
import { formaterPrix } from '../../../shared/format/devise'
import { listerLesResidences } from '../catalogue/api'
import { creerUnBaremeLivraisonRepas, listerLesBaremesLivraisonRepas, modifierUnBaremeLivraisonRepas } from './api'
import type { BaremeLivraisonRepas } from './types'

/** Barème de livraison repas : forfait par résidence, réservé administrateur (même patron que le barème des transferts). */
export function OngletBaremeLivraisonRepas() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [baremeEnEdition, setBaremeEnEdition] = useState<BaremeLivraisonRepas | null>(null)

  const baremes = useQuery({ queryKey: ['backoffice', 'tarification', 'baremes-livraison-repas'], queryFn: listerLesBaremesLivraisonRepas })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'tarification', 'baremes-livraison-repas'] })

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
          {t('backoffice.tarification.baremeLivraisonRepas.ajouter')}
        </Button>
      </div>

      <Table<BaremeLivraisonRepas>
        rowKey="id"
        size="small"
        loading={baremes.isPending}
        dataSource={baremes.data}
        pagination={false}
        onRow={(b) => ({
          onClick: () => {
            setBaremeEnEdition(b)
            setFormulaireOuvert(true)
          },
          style: { cursor: 'pointer' },
        })}
        locale={{ emptyText: <EtatVide titre={t('backoffice.tarification.baremeLivraisonRepas.aucun')} /> }}
        columns={[
          { title: t('backoffice.catalogue.residence.nom'), dataIndex: 'residence', render: (v: string | null) => v ?? '—' },
          { title: t('backoffice.tarification.baremeLivraisonRepas.forfait'), dataIndex: 'forfait', render: (v: number) => formaterPrix(v) },
        ]}
      />

      <Modal
        title={
          baremeEnEdition
            ? t('backoffice.tarification.baremeLivraisonRepas.modifier')
            : t('backoffice.tarification.baremeLivraisonRepas.ajouter')
        }
        open={formulaireOuvert}
        onCancel={() => setFormulaireOuvert(false)}
        footer={null}
        destroyOnHidden
      >
        <FormulaireBareme
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

function FormulaireBareme({ bareme, onEnregistre }: { bareme: BaremeLivraisonRepas | null; onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [residenceId, setResidenceId] = useState<number | undefined>(bareme?.residence_id)
  const [forfait, setForfait] = useState<number | null>(bareme?.forfait ?? null)
  const [erreur, setErreur] = useState<string | null>(null)

  const residences = useQuery({ queryKey: ['backoffice', 'residences', 'toutes'], queryFn: () => listerLesResidences({ par_page: 100 }) })

  const enregistrer = useMutation({
    mutationFn: () =>
      bareme
        ? modifierUnBaremeLivraisonRepas(bareme.id, { forfait: forfait ?? 0 })
        : creerUnBaremeLivraisonRepas({ residence_id: residenceId!, forfait: forfait ?? 0 }),
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.catalogue.residence.nom')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={residenceId}
        onChange={setResidenceId}
        disabled={!!bareme}
        loading={residences.isPending}
        options={residences.data?.elements.map((r) => ({ value: r.id, label: r.nom }))}
      />
      <Typography.Text>{t('backoffice.tarification.baremeLivraisonRepas.forfait')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={0} precision={0} value={forfait} onChange={setForfait} addonAfter="F" />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} disabled={!residenceId || forfait === null} onClick={() => enregistrer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
