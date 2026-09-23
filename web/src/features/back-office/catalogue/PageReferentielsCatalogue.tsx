import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Switch, Table, Tabs, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { creerUneLigneDeReferentiel, listerUnReferentiel, modifierUneLigneDeReferentiel } from './api'
import type { LigneReferentiel } from './types'

type Onglet = 'types-logement' | 'equipements'

/** Back office › Catalogue › Types de logement et équipements (CdC § 7.1, référentiels). */
export function PageReferentielsCatalogue() {
  const { t } = useTranslation()
  const [onglet, setOnglet] = useState<Onglet>('types-logement')
  const queryClient = useQueryClient()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [ligneEnEdition, setLigneEnEdition] = useState<LigneReferentiel | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const lignes = useQuery({
    queryKey: ['backoffice', 'referentiels', onglet],
    queryFn: () => listerUnReferentiel(onglet, { par_page: 100 }),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'referentiels', onglet] })

  return (
    <div>
      <Typography.Title level={3}>{t('backoffice.catalogue.referentiels.titre')}</Typography.Title>
      <Tabs
        activeKey={onglet}
        onChange={(cle) => setOnglet(cle as Onglet)}
        items={[
          { key: 'types-logement', label: t('backoffice.catalogue.referentiels.typesLogement') },
          { key: 'equipements', label: t('backoffice.catalogue.referentiels.equipements') },
        ]}
      />

      <Space style={{ marginBottom: 16 }}>
        <BoutonsExport url={`/backoffice/referentiels/${onglet}/export`} filtres={{}} nomFichier={onglet} />
        <Button
          type="primary"
          onClick={() => {
            setLigneEnEdition(null)
            setFormulaireOuvert(true)
          }}
        >
          {t('backoffice.catalogue.referentiels.ajouter')}
        </Button>
      </Space>

      {onglet === 'types-logement' ? (
        <Table<LigneReferentiel>
          rowKey="id"
          loading={lignes.isPending}
          dataSource={lignes.data?.elements}
          pagination={{ pageSize: 5, showSizeChanger: true, pageSizeOptions: [5, 10, 20, 50, 100] }}
          onRow={(ligne) => ({
            onClick: () => {
              setLigneEnEdition(ligne)
              setFormulaireOuvert(true)
            },
            style: { cursor: 'pointer' },
          })}
          columns={[
            { title: t('backoffice.catalogue.referentiels.code'), dataIndex: 'code' },
            { title: t('backoffice.catalogue.referentiels.nom'), dataIndex: 'nom' },
            { title: t('backoffice.catalogue.referentiels.nombrePieces'), dataIndex: 'nombre_pieces' },
            { title: t('backoffice.catalogue.referentiels.actif'), dataIndex: 'actif', render: (v: boolean) => (v ? '✓' : '—') },
          ]}
        />
      ) : (
        <Table<LigneReferentiel>
          rowKey="id"
          loading={lignes.isPending}
          dataSource={lignes.data?.elements}
          pagination={{ pageSize: 5, showSizeChanger: true, pageSizeOptions: [5, 10, 20, 50, 100] }}
          onRow={(ligne) => ({
            onClick: () => {
              setLigneEnEdition(ligne)
              setFormulaireOuvert(true)
            },
            style: { cursor: 'pointer' },
          })}
          columns={[
            { title: t('backoffice.catalogue.referentiels.nom'), dataIndex: 'nom' },
            { title: t('backoffice.catalogue.referentiels.portee'), dataIndex: 'portee' },
            {
              title: t('backoffice.catalogue.referentiels.filtreRecherche'),
              dataIndex: 'filtre_recherche',
              render: (v: boolean) => (v ? '✓' : '—'),
            },
            { title: t('backoffice.catalogue.referentiels.actif'), dataIndex: 'actif', render: (v: boolean) => (v ? '✓' : '—') },
          ]}
        />
      )}

      <Modal
        title={ligneEnEdition ? t('backoffice.catalogue.referentiels.modifier') : t('backoffice.catalogue.referentiels.ajouter')}
        open={formulaireOuvert}
        onCancel={() => {
          setFormulaireOuvert(false)
          setErreur(null)
        }}
        footer={null}
        destroyOnHidden
      >
        {onglet === 'types-logement' ? (
          <FormulaireTypeLogement
            ligne={ligneEnEdition}
            erreur={erreur}
            onErreur={setErreur}
            onEnregistre={() => {
              setFormulaireOuvert(false)
              void invalider()
            }}
          />
        ) : (
          <FormulaireEquipement
            ligne={ligneEnEdition}
            erreur={erreur}
            onErreur={setErreur}
            onEnregistre={() => {
              setFormulaireOuvert(false)
              void invalider()
            }}
          />
        )}
      </Modal>
    </div>
  )
}

interface ProprietesFormulaire {
  ligne: LigneReferentiel | null
  erreur: string | null
  onErreur: (e: string | null) => void
  onEnregistre: () => void
}

function FormulaireTypeLogement({ ligne, erreur, onErreur, onEnregistre }: ProprietesFormulaire) {
  const { t } = useTranslation()
  const [code, setCode] = useState((ligne?.code as string) ?? '')
  const [nom, setNom] = useState((ligne?.nom as string) ?? '')
  const [nombrePieces, setNombrePieces] = useState<number | null>((ligne?.nombre_pieces as number) ?? null)
  const [actif, setActif] = useState((ligne?.actif as boolean) ?? true)
  const [envoiEnCours, setEnvoiEnCours] = useState(false)

  const soumettre = async (): Promise<void> => {
    onErreur(null)
    setEnvoiEnCours(true)
    try {
      const champs = { code, nom, nombre_pieces: nombrePieces ?? undefined, actif }
      if (ligne) await modifierUneLigneDeReferentiel('types-logement', ligne.id, champs)
      else await creerUneLigneDeReferentiel('types-logement', champs)
      onEnregistre()
    } catch (e) {
      onErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    } finally {
      setEnvoiEnCours(false)
    }
  }

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.catalogue.referentiels.code')}</Typography.Text>
      <Input value={code} onChange={(e) => setCode(e.target.value)} placeholder="appartement" />
      <Typography.Text>{t('backoffice.catalogue.referentiels.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.catalogue.referentiels.nombrePieces')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} max={30} value={nombrePieces} onChange={setNombrePieces} />
      <Space>
        <Switch checked={actif} onChange={setActif} />
        <Typography.Text>{t('backoffice.catalogue.referentiels.actif')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={envoiEnCours} onClick={() => void soumettre()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}

function FormulaireEquipement({ ligne, erreur, onErreur, onEnregistre }: ProprietesFormulaire) {
  const { t } = useTranslation()
  const [nom, setNom] = useState((ligne?.nom as string) ?? '')
  const [portee, setPortee] = useState<string>((ligne?.portee as string) ?? 'logement')
  const [filtreRecherche, setFiltreRecherche] = useState((ligne?.filtre_recherche as boolean) ?? false)
  const [actif, setActif] = useState((ligne?.actif as boolean) ?? true)
  const [envoiEnCours, setEnvoiEnCours] = useState(false)

  const soumettre = async (): Promise<void> => {
    onErreur(null)
    setEnvoiEnCours(true)
    try {
      const champs = { nom, portee, filtre_recherche: filtreRecherche, actif }
      if (ligne) await modifierUneLigneDeReferentiel('equipements', ligne.id, champs)
      else await creerUneLigneDeReferentiel('equipements', champs)
      onEnregistre()
    } catch (e) {
      onErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    } finally {
      setEnvoiEnCours(false)
    }
  }

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.catalogue.referentiels.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.catalogue.referentiels.portee')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={portee}
        onChange={setPortee}
        options={[
          { value: 'logement', label: t('backoffice.catalogue.referentiels.porteeLogement') },
          { value: 'residence', label: t('backoffice.catalogue.referentiels.porteeResidence') },
        ]}
      />
      <Space>
        <Switch checked={filtreRecherche} onChange={setFiltreRecherche} />
        <Typography.Text>{t('backoffice.catalogue.referentiels.filtreRecherche')}</Typography.Text>
      </Space>
      <Space>
        <Switch checked={actif} onChange={setActif} />
        <Typography.Text>{t('backoffice.catalogue.referentiels.actif')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={envoiEnCours} onClick={() => void soumettre()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
