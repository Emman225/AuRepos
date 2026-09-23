import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Switch, Table, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { Modal } from '../../shared/composants/PopupModal'
import { formaterPrix } from '../../shared/format/devise'
import { ajouterUnProduit, maCarte, modifierUnProduit } from './api'
import type { CategorieProduit, ProduitRepas, SaisieProduitRepas } from './types'

/** Espace restaurateur › SA carte (CdC — espace restaurateur : « carte, stock… »). CRUD de ses propres produits uniquement. */
export function PageMaCarte() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [produitEnEdition, setProduitEnEdition] = useState<ProduitRepas | null>(null)

  const produits = useQuery({ queryKey: ['restaurateur', 'produits'], queryFn: maCarte })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['restaurateur', 'produits'] })

  return (
    <div>
      <EnTeteDePage
        titre={t('restaurateur.menu.carte')}
        actions={
          <Button
            type="primary"
            onClick={() => {
              setProduitEnEdition(null)
              setFormulaireOuvert(true)
            }}
          >
            {t('backoffice.restaurateurs.ajouterUnProduit')}
          </Button>
        }
      />

      <Table<ProduitRepas>
        rowKey="id"
        loading={produits.isPending}
        dataSource={produits.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('backoffice.restaurateurs.carteVide')} /> }}
        onRow={(p) => ({
          onClick: () => {
            setProduitEnEdition(p)
            setFormulaireOuvert(true)
          },
          style: { cursor: 'pointer' },
        })}
        columns={[
          { title: t('backoffice.restaurateurs.produit.nom'), dataIndex: 'nom' },
          {
            title: t('backoffice.restaurateurs.produit.categorie'),
            dataIndex: 'categorie',
            render: (v: CategorieProduit) => (v === 'plat' ? t('backoffice.restaurateurs.produit.plat') : t('backoffice.restaurateurs.produit.boisson')),
          },
          { title: t('backoffice.restaurateurs.produit.prixAchat'), dataIndex: 'prix_restaurateur', render: (v: number) => formaterPrix(v) },
          {
            title: t('restaurateur.carte.prixVente'),
            dataIndex: 'prix_vente',
            render: (v: number | null) => (v !== null ? formaterPrix(v) : '—'),
          },
          {
            title: t('backoffice.restaurateurs.produit.disponible'),
            dataIndex: 'disponible',
            render: (v: boolean) => <Tag color={v ? 'green' : 'default'}>{v ? '✓' : '—'}</Tag>,
          },
        ]}
      />

      <Modal
        title={produitEnEdition ? t('backoffice.restaurateurs.produit.modifier') : t('backoffice.restaurateurs.produit.ajouter')}
        open={formulaireOuvert}
        onCancel={() => setFormulaireOuvert(false)}
        footer={null}
        destroyOnHidden
      >
        <FormulaireProduit
          produit={produitEnEdition}
          onEnregistre={() => {
            setFormulaireOuvert(false)
            void invalider()
          }}
        />
      </Modal>
    </div>
  )
}

function FormulaireProduit({ produit, onEnregistre }: { produit: ProduitRepas | null; onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [nom, setNom] = useState(produit?.nom ?? '')
  const [description, setDescription] = useState(produit?.description ?? '')
  const [categorie, setCategorie] = useState<CategorieProduit>(produit?.categorie ?? 'plat')
  const [prix, setPrix] = useState<number | null>(produit?.prix_restaurateur ?? null)
  const [disponible, setDisponible] = useState(produit?.disponible ?? true)
  const [erreur, setErreur] = useState<string | null>(null)

  const enregistrer = useMutation({
    mutationFn: () => {
      const saisie: SaisieProduitRepas = { nom, description: description || undefined, categorie, prix_restaurateur: prix ?? 0, disponible }
      return produit ? modifierUnProduit(produit.id, saisie) : ajouterUnProduit(saisie)
    },
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.restaurateurs.produit.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.restaurateurs.produit.categorie')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={categorie}
        onChange={setCategorie}
        options={[
          { value: 'plat', label: t('backoffice.restaurateurs.produit.plat') },
          { value: 'boisson', label: t('backoffice.restaurateurs.produit.boisson') },
        ]}
      />
      <Typography.Text>{t('backoffice.restaurateurs.produit.prixAchat')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={0} value={prix} onChange={setPrix} addonAfter="F" />
      <Typography.Text>{t('backoffice.catalogue.residence.description')}</Typography.Text>
      <Input.TextArea value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
      <Space>
        <Switch checked={disponible} onChange={setDisponible} />
        <Typography.Text>{t('backoffice.restaurateurs.produit.disponible')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} disabled={!nom || prix === null} onClick={() => enregistrer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
