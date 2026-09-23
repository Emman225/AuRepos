import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { usePagination } from '../../../shared/composants/usePagination'
import { listerLesResidences } from '../catalogue/api'
import type { Residence } from '../catalogue/types'
import { creerUnCompteDePersonnel, listerLePersonnel, listerLesAgences, PROFILS_PERSONNEL, rattacherAuxResidences } from './api'
import type { Personnel, ProfilPersonnel, SaisiePersonnel, StatutCompte } from './types'

const PROFILS_AVEC_AGENCE_OBLIGATOIRE: ProfilPersonnel[] = ['administrateur', 'gestionnaire']

/** Back office › Personnel : administrateurs, gestionnaires, gouvernantes, agents d'assistance (CdC § 9.5). */
export function OngletPersonnel() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [recherche, setRecherche] = useState('')
  const [profil, setProfil] = useState<ProfilPersonnel | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [nouveauOuvert, setNouveauOuvert] = useState(false)
  const [residencesOuvertPour, setResidencesOuvertPour] = useState<Personnel | null>(null)

  const filtres = { recherche: recherche || undefined, profil, page, par_page: parPage }
  const personnel = useQuery({ queryKey: ['backoffice', 'personnel', filtres], queryFn: () => listerLePersonnel(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'personnel'] })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Space wrap>
          <Input.Search
            placeholder={t('backoffice.personnel.rechercher')}
            value={recherche}
            onChange={(e) => {
              setRecherche(e.target.value)
              reinitialiser()
            }}
            style={{ width: 240 }}
            allowClear
          />
          <Select
            style={{ width: 220 }}
            allowClear
            placeholder={t('backoffice.personnel.profil')}
            value={profil}
            onChange={(v) => {
              setProfil(v)
              reinitialiser()
            }}
            options={PROFILS_PERSONNEL.map((p) => ({ value: p, label: t(`backoffice.personnel.profils.${p}`) }))}
          />
        </Space>
        <Space>
          <BoutonsExport
            url="/backoffice/personnel/export"
            filtres={{ recherche: recherche || undefined, profil }}
            nomFichier="Personnel"
          />
          <Button type="primary" onClick={() => setNouveauOuvert(true)}>
            {t('backoffice.personnel.nouveauCompte')}
          </Button>
        </Space>
      </div>

      <Table<Personnel>
        rowKey="id"
        size="small"
        loading={personnel.isPending}
        dataSource={personnel.data?.elements}
        pagination={propsPagination(personnel.data?.pagination.total)}
        onRow={(p) => (p.profil === 'gestionnaire' ? { onClick: () => setResidencesOuvertPour(p), style: { cursor: 'pointer' } } : {})}
        columns={[
          { title: t('backoffice.personnel.nom'), dataIndex: 'nom_complet' },
          { title: t('backoffice.clients.email'), dataIndex: 'email' },
          { title: t('backoffice.personnel.identifiant'), dataIndex: 'identifiant' },
          { title: t('backoffice.personnel.profil'), dataIndex: 'profil_libelle' },
          { title: t('backoffice.personnel.agence'), key: 'agence', render: (_, p) => p.agence?.nom ?? '—' },
          {
            title: t('backoffice.personnel.residences'),
            key: 'residences',
            render: (_, p) => (p.profil === 'gestionnaire' ? `${p.residences?.length ?? 0}` : '—'),
          },
          {
            title: t('backoffice.clients.statut'),
            dataIndex: 'statut',
            render: (v: StatutCompte) => <StatutBadge domaine="compte" code={v} libelle={t(`backoffice.clients.statuts.${v}`)} />,
          },
        ]}
      />

      <Modal title={t('backoffice.personnel.nouveauCompte')} open={nouveauOuvert} onCancel={() => setNouveauOuvert(false)} footer={null} destroyOnHidden>
        <FormulaireNouveauCompte
          onCree={() => {
            setNouveauOuvert(false)
            void invalider()
          }}
        />
      </Modal>

      <Modal
        title={residencesOuvertPour ? t('backoffice.personnel.residencesDe', { nom: residencesOuvertPour.nom_complet }) : ''}
        open={residencesOuvertPour !== null}
        onCancel={() => setResidencesOuvertPour(null)}
        footer={null}
        destroyOnHidden
      >
        {residencesOuvertPour && (
          <FormulaireResidences
            personnel={residencesOuvertPour}
            onEnregistre={() => {
              setResidencesOuvertPour(null)
              void invalider()
            }}
          />
        )}
      </Modal>
    </div>
  )
}

function FormulaireNouveauCompte({ onCree }: { onCree: () => void }) {
  const { t } = useTranslation()
  const [nom, setNom] = useState('')
  const [prenoms, setPrenoms] = useState('')
  const [email, setEmail] = useState('')
  const [telephone, setTelephone] = useState('')
  const [profil, setProfil] = useState<ProfilPersonnel>('gestionnaire')
  const [agenceId, setAgenceId] = useState<number | undefined>(undefined)
  const [residences, setResidences] = useState<number[]>([])
  const [erreur, setErreur] = useState<string | null>(null)

  const agences = useQuery({ queryKey: ['backoffice', 'agences', 'toutes'], queryFn: () => listerLesAgences({ par_page: 100, active: true }) })
  const listeDesResidences = useQuery({ queryKey: ['backoffice', 'residences', 'toutes'], queryFn: () => listerLesResidences({ par_page: 100 }) })

  const creer = useMutation({
    mutationFn: () => {
      const saisie: SaisiePersonnel = { nom, prenoms: prenoms || undefined, email, telephone: telephone || undefined, profil, agence_id: agenceId }
      if (profil === 'gestionnaire' && residences.length > 0) saisie.residences = residences
      return creerUnCompteDePersonnel(saisie)
    },
    onSuccess: onCree,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const agenceObligatoire = PROFILS_AVEC_AGENCE_OBLIGATOIRE.includes(profil)

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.personnel.nom')}</Typography.Text>
      <Input value={nom} onChange={(e) => setNom(e.target.value)} />
      <Typography.Text>{t('backoffice.personnel.prenoms')}</Typography.Text>
      <Input value={prenoms} onChange={(e) => setPrenoms(e.target.value)} />
      <Typography.Text>{t('backoffice.clients.email')}</Typography.Text>
      <Input value={email} onChange={(e) => setEmail(e.target.value)} />
      <Typography.Text>{t('backoffice.personnel.telephone')}</Typography.Text>
      <Input value={telephone} onChange={(e) => setTelephone(e.target.value)} placeholder="+2250700000000" />
      <Typography.Text>{t('backoffice.personnel.profil')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={profil}
        onChange={setProfil}
        options={PROFILS_PERSONNEL.map((p) => ({ value: p, label: t(`backoffice.personnel.profils.${p}`) }))}
      />
      <Typography.Text>
        {t('backoffice.personnel.agence')}
        {agenceObligatoire ? ' *' : ` (${t('backoffice.personnel.facultatif')})`}
      </Typography.Text>
      <Select
        style={{ width: '100%' }}
        allowClear
        value={agenceId}
        onChange={setAgenceId}
        loading={agences.isPending}
        options={agences.data?.elements.map((a) => ({ value: a.id, label: a.nom }))}
      />
      {profil === 'gestionnaire' && (
        <>
          <Typography.Text>{t('backoffice.personnel.residencesConfiees')}</Typography.Text>
          <Select
            mode="multiple"
            style={{ width: '100%' }}
            value={residences}
            onChange={setResidences}
            loading={listeDesResidences.isPending}
            options={listeDesResidences.data?.elements.map((r: Residence) => ({ value: r.id, label: r.nom }))}
          />
        </>
      )}
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button
        type="primary"
        loading={creer.isPending}
        disabled={!nom || !email || (agenceObligatoire && !agenceId)}
        onClick={() => creer.mutate()}
      >
        {t('backoffice.personnel.creer')}
      </Button>
    </Space>
  )
}

function FormulaireResidences({ personnel, onEnregistre }: { personnel: Personnel; onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [residences, setResidences] = useState<number[]>(personnel.residences?.map((r) => r.id) ?? [])
  const [erreur, setErreur] = useState<string | null>(null)

  const listeDesResidences = useQuery({ queryKey: ['backoffice', 'residences', 'toutes'], queryFn: () => listerLesResidences({ par_page: 100 }) })

  const enregistrer = useMutation({
    mutationFn: () => rattacherAuxResidences(personnel.id, residences),
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Select
        mode="multiple"
        style={{ width: '100%' }}
        value={residences}
        onChange={setResidences}
        loading={listeDesResidences.isPending}
        options={listeDesResidences.data?.elements.map((r: Residence) => ({ value: r.id, label: r.nom }))}
      />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} onClick={() => enregistrer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
