import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Drawer, Input, InputNumber, Select, Space, Switch, Table, Tabs, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../../shared/composants/EtatVide'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { couleurs } from '../../../shared/theme/jetons'
import {
  ajouterUnProduitALaCarte,
  detteDuRestaurateur,
  laCarteDuRestaurateur,
  listerLesRestaurateurs,
  modifierUnProduitDeLaCarte,
  proposerLePourcentagePlateforme,
} from './api'
import { FormulaireRestaurateur } from './FormulaireRestaurateur'
import type { CategorieProduit, ProduitRepas, Restaurateur, SaisieProduitRepas } from './types'

/** Back office › Restaurateurs partenaires : fiche, carte de produits, dette, pourcentage plateforme (double validation). */
export function PageRestaurateurs() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [restaurateurEnEdition, setRestaurateurEnEdition] = useState<Restaurateur | null>(null)
  const [restaurateurConsulte, setRestaurateurConsulte] = useState<Restaurateur | null>(null)

  const filtres = { recherche: recherche || undefined, page, par_page: parPage }
  const restaurateurs = useQuery({ queryKey: ['backoffice', 'restaurateurs', filtres], queryFn: () => listerLesRestaurateurs(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'restaurateurs'] })

  return (
    <div>
      <EnTeteDePage
        titre={t('backoffice.menu.restaurateurs')}
        actions={
          <Button type="primary" onClick={() => { setRestaurateurEnEdition(null); setFormulaireOuvert(true) }}>
            {t('backoffice.restaurateurs.creer')}
          </Button>
        }
      />

      <Space wrap style={{ marginBottom: 16 }}>
        <Input.Search
          placeholder={t('backoffice.clients.recherche')}
          value={recherche}
          onChange={(e) => {
            setRecherche(e.target.value)
            reinitialiser()
          }}
          style={{ width: 260 }}
          allowClear
        />
      </Space>

      <Table<Restaurateur>
        rowKey="id"
        loading={restaurateurs.isPending}
        dataSource={restaurateurs.data?.elements}
        pagination={propsPagination(restaurateurs.data?.pagination.total)}
        onRow={(r) => ({ onClick: () => setRestaurateurConsulte(r), style: { cursor: 'pointer' } })}
        locale={{ emptyText: <EtatVide titre={t('backoffice.restaurateurs.aucun')} /> }}
        columns={[
          { title: t('backoffice.proprietaires.nom'), dataIndex: ['compte', 'nom_complet'] },
          { title: t('backoffice.proprietaires.courriel'), dataIndex: ['compte', 'email'] },
          {
            title: t('backoffice.restaurateurs.pourcentagePlateforme'),
            dataIndex: 'pourcentage_plateforme',
            render: (v: number | null) => (v !== null ? `${v} %` : t('backoffice.restaurateurs.pourcentageNonFixe')),
          },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: ['compte', 'statut'],
            render: (v: string) => <StatutBadge domaine="compte" code={v} libelle={t(`backoffice.clients.statuts.${v}`)} />,
          },
          {
            title: t('backoffice.apporteurs.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => (
              <StatutBadge domaine="actif" code={v ? 'actif' : 'inactif'} libelle={v ? t('backoffice.catalogue.residence.oui') : t('backoffice.catalogue.residence.non')} />
            ),
          },
          {
            title: '',
            key: 'actions',
            render: (_, r) => (
              <Button
                size="small"
                onClick={(e) => {
                  e.stopPropagation()
                  setRestaurateurEnEdition(r)
                  setFormulaireOuvert(true)
                }}
              >
                {t('backoffice.restaurateurs.modifier')}
              </Button>
            ),
          },
        ]}
      />

      <FormulaireRestaurateur
        ouvert={formulaireOuvert}
        restaurateur={restaurateurEnEdition}
        onFermer={() => setFormulaireOuvert(false)}
        onEnregistre={() => {
          setFormulaireOuvert(false)
          void invalider()
        }}
      />

      <Drawer title={restaurateurConsulte?.compte.nom_complet} open={restaurateurConsulte !== null} onClose={() => setRestaurateurConsulte(null)} width={560}>
        {restaurateurConsulte && (
          <Tabs
            items={[
              { key: 'carte', label: t('backoffice.restaurateurs.carte'), children: <OngletCarte restaurateur={restaurateurConsulte} /> },
              { key: 'dette', label: t('backoffice.restaurateurs.dette'), children: <OngletDette restaurateurId={restaurateurConsulte.id} /> },
              {
                key: 'pourcentage',
                label: t('backoffice.restaurateurs.pourcentagePlateforme'),
                children: <OngletPourcentage restaurateur={restaurateurConsulte} onPropose={() => void invalider()} />,
              },
            ]}
          />
        )}
      </Drawer>
    </div>
  )
}

function OngletDette({ restaurateurId }: { restaurateurId: number }) {
  const { t } = useTranslation()
  const dette = useQuery({ queryKey: ['backoffice', 'restaurateurs', restaurateurId, 'dette'], queryFn: () => detteDuRestaurateur(restaurateurId) })

  if (dette.isPending || !dette.data) return null

  return (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <div>
        <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
          {t('backoffice.restaurateurs.du')}
        </Typography.Text>
        <Typography.Text strong style={{ fontSize: 24, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
          {formaterPrix(dette.data.du)}
        </Typography.Text>
      </div>
      <div>
        <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
          {t('backoffice.restaurateurs.dejaVerse')}
        </Typography.Text>
        <Typography.Text strong style={{ fontSize: 18, fontVariantNumeric: 'tabular-nums' }}>
          {formaterPrix(dette.data.deja_verse)}
        </Typography.Text>
      </div>
    </Space>
  )
}

function OngletPourcentage({ restaurateur, onPropose }: { restaurateur: Restaurateur; onPropose: () => void }) {
  const { t } = useTranslation()
  const [taux, setTaux] = useState<number | null>(null)
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const proposer = useMutation({
    mutationFn: () => proposerLePourcentagePlateforme(restaurateur.id, taux ?? 0, motif || undefined),
    onSuccess: (changement) => {
      setMessage(t('backoffice.restaurateurs.propositionEnvoyee', { montant: changement.valeur_proposee ?? '' }))
      setErreur(null)
      onPropose()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" size={16} style={{ width: '100%' }}>
      <Typography.Paragraph type="secondary">{t('backoffice.restaurateurs.pourcentageAide')}</Typography.Paragraph>
      <Typography.Text>
        {t('backoffice.restaurateurs.pourcentageActuel', { taux: restaurateur.pourcentage_plateforme ?? '—' })}
      </Typography.Text>
      <Space wrap>
        <InputNumber min={0} max={500} value={taux} onChange={setTaux} addonAfter="%" />
        <Input placeholder={t('backoffice.catalogue.prix.motif')} value={motif} onChange={(e) => setMotif(e.target.value)} style={{ width: 220 }} />
        <Button type="primary" loading={proposer.isPending} disabled={taux === null} onClick={() => proposer.mutate()}>
          {t('backoffice.tarification.pourcentage.proposer')}
        </Button>
      </Space>
      {message && <Alert type="success" showIcon title={message} />}
      {erreur && <Alert type="error" showIcon title={erreur} />}
    </Space>
  )
}

function OngletCarte({ restaurateur }: { restaurateur: Restaurateur }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [produitEnEdition, setProduitEnEdition] = useState<ProduitRepas | null>(null)

  const produits = useQuery({ queryKey: ['backoffice', 'restaurateurs', restaurateur.id, 'produits'], queryFn: () => laCarteDuRestaurateur(restaurateur.id) })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'restaurateurs', restaurateur.id, 'produits'] })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: 12 }}>
        <Button
          size="small"
          type="primary"
          onClick={() => {
            setProduitEnEdition(null)
            setFormulaireOuvert(true)
          }}
        >
          {t('backoffice.restaurateurs.ajouterUnProduit')}
        </Button>
      </div>
      <Table<ProduitRepas>
        rowKey="id"
        size="small"
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
          restaurateurId={restaurateur.id}
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

function FormulaireProduit({ restaurateurId, produit, onEnregistre }: { restaurateurId: number; produit: ProduitRepas | null; onEnregistre: () => void }) {
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
      return produit ? modifierUnProduitDeLaCarte(restaurateurId, produit.id, saisie) : ajouterUnProduitALaCarte(restaurateurId, saisie)
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
