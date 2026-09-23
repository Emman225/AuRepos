import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, DatePicker, Input, InputNumber, Popconfirm, Select, Space, Switch, Table, Tabs, Typography } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi, lire } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { creerUneLigneDeReferentiel, listerUnReferentiel, modifierUneLigneDeReferentiel, supprimerUneLigneDeReferentiel } from './api'
import type { LigneReferentiel } from './types'

type Onglet = 'saisons' | 'tranches-duree' | 'supplements'

interface TypeLogementChoix {
  id: number
  nom: string
}

/** Back office › Tarification › Saisons, tranches et suppléments (CdC § 7.3, réservé administrateur). */
export function OngletReferentiels() {
  const { t } = useTranslation()
  const [onglet, setOnglet] = useState<Onglet>('saisons')
  const queryClient = useQueryClient()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [ligneEnEdition, setLigneEnEdition] = useState<LigneReferentiel | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  const lignes = useQuery({
    queryKey: ['backoffice', 'referentiels', onglet],
    queryFn: () => listerUnReferentiel(onglet, { par_page: 100 }),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'referentiels', onglet] })

  const supprimer = useMutation({
    mutationFn: (id: number) => supprimerUneLigneDeReferentiel(onglet, id),
    onSuccess: invalider,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <div>
      <Tabs
        activeKey={onglet}
        onChange={(cle) => setOnglet(cle as Onglet)}
        items={[
          { key: 'saisons', label: t('backoffice.tarification.referentiels.saisons') },
          { key: 'tranches-duree', label: t('backoffice.tarification.referentiels.tranchesDuree') },
          { key: 'supplements', label: t('backoffice.tarification.referentiels.supplements') },
        ]}
      />

      {onglet !== 'supplements' && <Alert style={{ marginBottom: 16 }} type="warning" showIcon title={t('backoffice.tarification.referentiels.avertissementCascade')} />}
      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} />}

      <Space style={{ marginBottom: 16 }}>
        <BoutonsExport url={`/backoffice/referentiels/${onglet}/export`} filtres={{}} nomFichier={onglet} />
        <Button
          type="primary"
          onClick={() => {
            setLigneEnEdition(null)
            setFormulaireOuvert(true)
          }}
        >
          {t('backoffice.tarification.referentiels.ajouter')}
        </Button>
      </Space>

      <Table<LigneReferentiel>
        rowKey="id"
        size="small"
        loading={lignes.isPending}
        dataSource={lignes.data?.elements}
        pagination={{ pageSize: 5, showSizeChanger: true, pageSizeOptions: [5, 10, 20, 50, 100] }}
        columns={[
          ...colonnesPourOnglet(onglet, t),
          {
            title: '',
            key: 'actions',
            render: (_, l) => (
              <Popconfirm
                title={t('backoffice.tarification.referentiels.confirmerSuppression')}
                onConfirm={() => supprimer.mutate(l.id)}
              >
                <Button size="small" danger onClick={(e) => e.stopPropagation()}>
                  {t('backoffice.tarification.referentiels.supprimer')}
                </Button>
              </Popconfirm>
            ),
          },
        ]}
        onRow={(ligne) => ({
          onClick: () => {
            setLigneEnEdition(ligne)
            setFormulaireOuvert(true)
          },
          style: { cursor: 'pointer' },
        })}
      />

      <Modal
        title={ligneEnEdition ? t('backoffice.tarification.referentiels.modifier') : t('backoffice.tarification.referentiels.ajouter')}
        open={formulaireOuvert}
        onCancel={() => setFormulaireOuvert(false)}
        footer={null}
        destroyOnHidden
      >
        {onglet === 'saisons' && (
          <FormulaireSaison ligne={ligneEnEdition} onEnregistre={() => { setFormulaireOuvert(false); void invalider() }} />
        )}
        {onglet === 'tranches-duree' && (
          <FormulaireTranche ligne={ligneEnEdition} onEnregistre={() => { setFormulaireOuvert(false); void invalider() }} />
        )}
        {onglet === 'supplements' && (
          <FormulaireSupplement ligne={ligneEnEdition} onEnregistre={() => { setFormulaireOuvert(false); void invalider() }} />
        )}
      </Modal>
    </div>
  )
}

function colonnesPourOnglet(onglet: Onglet, t: (cle: string, options?: Record<string, unknown>) => string) {
  if (onglet === 'saisons') {
    return [
      { title: t('backoffice.tarification.referentiels.nom'), dataIndex: 'nom' },
      {
        title: t('backoffice.tarification.referentiels.categorie'),
        dataIndex: 'categorie',
        render: (v: string) => libelleCategorie(v, t),
      },
      { title: t('backoffice.tarification.referentiels.periode'), key: 'periode', render: (_: unknown, l: LigneReferentiel) => `${l.date_debut} — ${l.date_fin}` },
      { title: t('backoffice.tarification.referentiels.actif'), dataIndex: 'actif', render: (v: boolean) => (v ? '✓' : '—') },
    ]
  }
  if (onglet === 'tranches-duree') {
    return [
      { title: t('backoffice.tarification.referentiels.nom'), dataIndex: 'nom' },
      {
        title: t('backoffice.tarification.referentiels.nuits'),
        key: 'nuits',
        render: (_: unknown, l: LigneReferentiel) => `${l.nuits_min} — ${l.nuits_max ?? '∞'}`,
      },
      { title: t('backoffice.tarification.referentiels.actif'), dataIndex: 'actif', render: (v: boolean) => (v ? '✓' : '—') },
    ]
  }
  return [
    { title: t('backoffice.tarification.referentiels.code'), dataIndex: 'code', render: (v: string) => libelleCodeSupplement(v, t) },
    { title: t('backoffice.tarification.referentiels.nom'), dataIndex: 'nom' },
    { title: t('backoffice.tarification.referentiels.mode'), dataIndex: 'mode', render: (v: string) => libelleModeSupplement(v, t) },
    { title: t('backoffice.tarification.referentiels.montant'), dataIndex: 'montant' },
    { title: t('backoffice.tarification.referentiels.actif'), dataIndex: 'actif', render: (v: boolean) => (v ? '✓' : '—') },
  ]
}

function libelleCategorie(v: string, t: (cle: string) => string): string {
  if (v === 'basse') return t('backoffice.tarification.referentiels.categorieBasse')
  if (v === 'haute') return t('backoffice.tarification.referentiels.categorieHaute')
  return t('backoffice.tarification.referentiels.categorieEvenement')
}

function libelleCodeSupplement(v: string, t: (cle: string) => string): string {
  const cles: Record<string, string> = {
    occupant_supplementaire: 'backoffice.tarification.referentiels.codeOccupantSupplementaire',
    week_end: 'backoffice.tarification.referentiels.codeWeekEnd',
    arrivee_tardive: 'backoffice.tarification.referentiels.codeArriveeTardive',
    depart_tardif: 'backoffice.tarification.referentiels.codeDepartTardif',
  }
  return t(cles[v] ?? v)
}

function libelleModeSupplement(v: string, t: (cle: string) => string): string {
  const cles: Record<string, string> = {
    par_nuit_et_par_personne: 'backoffice.tarification.referentiels.modeParNuitEtParPersonne',
    par_nuit: 'backoffice.tarification.referentiels.modeParNuit',
    forfait: 'backoffice.tarification.referentiels.modeForfait',
  }
  return t(cles[v] ?? v)
}

interface ProprietesFormulaire {
  ligne: LigneReferentiel | null
  onEnregistre: () => void
}

function FormulaireSaison({ ligne, onEnregistre }: ProprietesFormulaire) {
  const { t } = useTranslation()
  const [nom, setNom] = useState((ligne?.nom as string) ?? '')
  const [categorie, setCategorie] = useState<string>((ligne?.categorie as string) ?? 'basse')
  const [periode, setPeriode] = useState<[dayjs.Dayjs, dayjs.Dayjs] | null>(
    ligne ? [dayjs(ligne.date_debut as string), dayjs(ligne.date_fin as string)] : null,
  )
  const [actif, setActif] = useState((ligne?.actif as boolean) ?? true)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoiEnCours, setEnvoiEnCours] = useState(false)

  const soumettre = async (): Promise<void> => {
    setErreur(null)
    setEnvoiEnCours(true)
    try {
      const champs = {
        nom,
        categorie,
        date_debut: periode ? periode[0].format('YYYY-MM-DD') : undefined,
        date_fin: periode ? periode[1].format('YYYY-MM-DD') : undefined,
        actif,
      }
      if (ligne) await modifierUneLigneDeReferentiel('saisons', ligne.id, champs)
      else await creerUneLigneDeReferentiel('saisons', champs)
      onEnregistre()
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    } finally {
      setEnvoiEnCours(false)
    }
  }

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.tarification.referentiels.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.tarification.referentiels.categorie')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={categorie}
        onChange={setCategorie}
        options={[
          { value: 'basse', label: t('backoffice.tarification.referentiels.categorieBasse') },
          { value: 'haute', label: t('backoffice.tarification.referentiels.categorieHaute') },
          { value: 'evenement', label: t('backoffice.tarification.referentiels.categorieEvenement') },
        ]}
      />
      <Typography.Text>{t('backoffice.tarification.referentiels.periode')}</Typography.Text>
      <DatePicker.RangePicker
        style={{ width: '100%' }}
        format="DD/MM/YYYY"
        value={periode}
        onChange={(v) => setPeriode(v && v[0] && v[1] ? [v[0], v[1]] : null)}
      />
      <Space>
        <Switch checked={actif} onChange={setActif} />
        <Typography.Text>{t('backoffice.tarification.referentiels.actif')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={envoiEnCours} disabled={!nom || !periode} onClick={() => void soumettre()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}

function FormulaireTranche({ ligne, onEnregistre }: ProprietesFormulaire) {
  const { t } = useTranslation()
  const [nom, setNom] = useState((ligne?.nom as string) ?? '')
  const [nuitsMin, setNuitsMin] = useState<number | null>((ligne?.nuits_min as number) ?? 1)
  const [nuitsMax, setNuitsMax] = useState<number | null>((ligne?.nuits_max as number | null) ?? null)
  const [actif, setActif] = useState((ligne?.actif as boolean) ?? true)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoiEnCours, setEnvoiEnCours] = useState(false)

  const soumettre = async (): Promise<void> => {
    setErreur(null)
    setEnvoiEnCours(true)
    try {
      const champs = { nom, nuits_min: nuitsMin ?? undefined, nuits_max: nuitsMax, actif }
      if (ligne) await modifierUneLigneDeReferentiel('tranches-duree', ligne.id, champs)
      else await creerUneLigneDeReferentiel('tranches-duree', champs)
      onEnregistre()
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    } finally {
      setEnvoiEnCours(false)
    }
  }

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.tarification.referentiels.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.tarification.referentiels.nuitsMin')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} max={3650} value={nuitsMin} onChange={setNuitsMin} />
      <Typography.Text>{t('backoffice.tarification.referentiels.nuitsMax')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={nuitsMin ?? 1} max={3650} value={nuitsMax} onChange={setNuitsMax} />
      <Space>
        <Switch checked={actif} onChange={setActif} />
        <Typography.Text>{t('backoffice.tarification.referentiels.actif')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={envoiEnCours} disabled={!nom || !nuitsMin} onClick={() => void soumettre()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}

function FormulaireSupplement({ ligne, onEnregistre }: ProprietesFormulaire) {
  const { t } = useTranslation()
  const [code, setCode] = useState<string>((ligne?.code as string) ?? 'occupant_supplementaire')
  const [nom, setNom] = useState((ligne?.nom as string) ?? '')
  const [mode, setMode] = useState<string>((ligne?.mode as string) ?? 'par_nuit_et_par_personne')
  const [montant, setMontant] = useState<number | null>((ligne?.montant as number) ?? null)
  const [typeLogementId, setTypeLogementId] = useState<number | undefined>((ligne?.type_logement_id as number | undefined) ?? undefined)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoiEnCours, setEnvoiEnCours] = useState(false)

  const types = useQuery({ queryKey: ['referentiels', 'types-logement'], queryFn: () => lire<TypeLogementChoix[]>('/referentiels/types-logement') })

  const soumettre = async (): Promise<void> => {
    setErreur(null)
    setEnvoiEnCours(true)
    try {
      const champs = { code, nom, mode, montant: montant ?? undefined, type_logement_id: typeLogementId ?? null }
      if (ligne) await modifierUneLigneDeReferentiel('supplements', ligne.id, champs)
      else await creerUneLigneDeReferentiel('supplements', champs)
      onEnregistre()
    } catch (e) {
      setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
    } finally {
      setEnvoiEnCours(false)
    }
  }

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.tarification.referentiels.code')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={code}
        onChange={setCode}
        options={[
          { value: 'occupant_supplementaire', label: t('backoffice.tarification.referentiels.codeOccupantSupplementaire') },
          { value: 'week_end', label: t('backoffice.tarification.referentiels.codeWeekEnd') },
          { value: 'arrivee_tardive', label: t('backoffice.tarification.referentiels.codeArriveeTardive') },
          { value: 'depart_tardif', label: t('backoffice.tarification.referentiels.codeDepartTardif') },
        ]}
      />
      <Typography.Text>{t('backoffice.tarification.referentiels.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.tarification.referentiels.mode')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={mode}
        onChange={setMode}
        options={[
          { value: 'par_nuit_et_par_personne', label: t('backoffice.tarification.referentiels.modeParNuitEtParPersonne') },
          { value: 'par_nuit', label: t('backoffice.tarification.referentiels.modeParNuit') },
          { value: 'forfait', label: t('backoffice.tarification.referentiels.modeForfait') },
        ]}
      />
      <Typography.Text>{t('backoffice.tarification.referentiels.montant')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={0} step={100} value={montant} onChange={setMontant} />
      <Typography.Text>{t('backoffice.tarification.referentiels.typeLogement')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        allowClear
        placeholder={t('backoffice.tarification.referentiels.tousLesTypes')}
        value={typeLogementId}
        onChange={setTypeLogementId}
        loading={types.isPending}
        options={types.data?.map((v) => ({ value: v.id, label: v.nom }))}
      />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={envoiEnCours} disabled={!nom || montant === null} onClick={() => void soumettre()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
