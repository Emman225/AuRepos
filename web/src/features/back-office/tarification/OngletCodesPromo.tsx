import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, DatePicker, Input, InputNumber, Select, Space, Switch, Table, Tag, Typography } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { usePagination } from '../../../shared/composants/usePagination'
import { formaterPrix } from '../../../shared/format/devise'
import { listerLesResidences } from '../catalogue/api'
import { creerUnCodePromo, listerLesCodesPromo, modifierUnCodePromo } from './api'
import type { CodePromo, TypeDeCodePromo } from './types'

/** Back office › Tarification › Codes promo (CdC § 7.3, réservé administrateur). */
export function OngletCodesPromo() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [actif, setActif] = useState<boolean | undefined>(undefined)
  const { page, parPage, reinitialiser, propsPagination } = usePagination()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [codeEnEdition, setCodeEnEdition] = useState<CodePromo | null>(null)

  const filtres = { actif, page, par_page: parPage }
  const codes = useQuery({ queryKey: ['backoffice', 'tarification', 'codes-promo', filtres], queryFn: () => listerLesCodesPromo(filtres) })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'tarification', 'codes-promo'] })

  return (
    <div>
      <Space wrap style={{ marginBottom: 16, width: '100%', justifyContent: 'space-between' }}>
        <Select
          style={{ width: 180 }}
          allowClear
          placeholder={t('backoffice.agences.active')}
          value={actif}
          onChange={(v) => {
            setActif(v)
            reinitialiser()
          }}
          options={[
            { value: true, label: t('backoffice.agences.active') },
            { value: false, label: t('backoffice.agences.inactive') },
          ]}
        />
        <Space>
          <BoutonsExport url="/backoffice/codes-promo/export" filtres={{ actif }} nomFichier="CodesPromo" />
          <Button
            type="primary"
            onClick={() => {
              setCodeEnEdition(null)
              setFormulaireOuvert(true)
            }}
          >
            {t('backoffice.tarification.codesPromo.ajouter')}
          </Button>
        </Space>
      </Space>

      <Table<CodePromo>
        rowKey="id"
        size="small"
        loading={codes.isPending}
        dataSource={codes.data?.elements}
        pagination={propsPagination(codes.data?.pagination.total)}
        onRow={(c) => ({
          onClick: () => {
            setCodeEnEdition(c)
            setFormulaireOuvert(true)
          },
          style: { cursor: 'pointer' },
        })}
        columns={[
          { title: t('backoffice.tarification.codesPromo.code'), dataIndex: 'code' },
          {
            title: t('backoffice.tarification.codesPromo.type'),
            dataIndex: 'type',
            render: (v: TypeDeCodePromo) => (v === 'pourcentage' ? t('backoffice.tarification.codesPromo.typePourcentage') : t('backoffice.tarification.codesPromo.typeMontant')),
          },
          {
            title: t('backoffice.tarification.codesPromo.valeur'),
            dataIndex: 'valeur',
            render: (v: number, c) => (c.type === 'pourcentage' ? `${v} %` : formaterPrix(v)),
          },
          { title: t('backoffice.tarification.codesPromo.periode'), key: 'periode', render: (_, c) => `${c.date_debut} — ${c.date_fin}` },
          { title: t('backoffice.tarification.codesPromo.residence'), dataIndex: ['residence', 'nom'], render: (v: string | undefined) => v ?? '—' },
          {
            title: t('backoffice.tarification.codesPromo.valableAujourdhui'),
            dataIndex: 'valable_aujourd_hui',
            render: (v: boolean) => <Tag color={v ? 'green' : 'default'}>{v ? '✓' : '—'}</Tag>,
          },
        ]}
      />

      <Modal
        title={codeEnEdition ? t('backoffice.tarification.codesPromo.modifier') : t('backoffice.tarification.codesPromo.ajouter')}
        open={formulaireOuvert}
        onCancel={() => setFormulaireOuvert(false)}
        footer={null}
        destroyOnHidden
      >
        {codeEnEdition ? (
          <FormulaireModificationCode
            code={codeEnEdition}
            onEnregistre={() => {
              setFormulaireOuvert(false)
              void invalider()
            }}
          />
        ) : (
          <FormulaireNouveauCode
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

function FormulaireNouveauCode({ onEnregistre }: { onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [code, setCode] = useState('')
  const [type, setType] = useState<TypeDeCodePromo>('pourcentage')
  const [valeur, setValeur] = useState<number | null>(null)
  const [periode, setPeriode] = useState<[dayjs.Dayjs, dayjs.Dayjs] | null>(null)
  const [residenceId, setResidenceId] = useState<number | undefined>(undefined)
  const [description, setDescription] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const residences = useQuery({ queryKey: ['backoffice', 'residences', 'toutes'], queryFn: () => listerLesResidences({ par_page: 100 }) })

  const creer = useMutation({
    mutationFn: () =>
      creerUnCodePromo({
        code,
        type,
        valeur: valeur!,
        date_debut: periode![0].format('YYYY-MM-DD'),
        date_fin: periode![1].format('YYYY-MM-DD'),
        residence_id: residenceId,
        description: description || undefined,
      }),
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.tarification.codesPromo.code')}</Typography.Text>
      <Input value={code} onChange={(e) => setCode(e.target.value)} placeholder="NOEL2026" />
      <Typography.Text>{t('backoffice.tarification.codesPromo.type')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        value={type}
        onChange={setType}
        options={[
          { value: 'pourcentage', label: t('backoffice.tarification.codesPromo.typePourcentage') },
          { value: 'montant', label: t('backoffice.tarification.codesPromo.typeMontant') },
        ]}
      />
      <Typography.Text>{t('backoffice.tarification.codesPromo.valeur')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} min={1} max={type === 'pourcentage' ? 100 : undefined} value={valeur} onChange={setValeur} addonAfter={type === 'pourcentage' ? '%' : 'F'} />
      <Typography.Text>{t('backoffice.tarification.codesPromo.periode')}</Typography.Text>
      <DatePicker.RangePicker
        style={{ width: '100%' }}
        format="DD/MM/YYYY"
        value={periode}
        onChange={(v) => setPeriode(v && v[0] && v[1] ? [v[0], v[1]] : null)}
      />
      <Typography.Text>{t('backoffice.tarification.codesPromo.residence')}</Typography.Text>
      <Select
        style={{ width: '100%' }}
        allowClear
        value={residenceId}
        onChange={setResidenceId}
        loading={residences.isPending}
        options={residences.data?.elements.map((r) => ({ value: r.id, label: r.nom }))}
      />
      <Typography.Text>{t('backoffice.tarification.codesPromo.description')}</Typography.Text>
      <Input.TextArea value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={creer.isPending} disabled={!code || !valeur || !periode} onClick={() => creer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}

function FormulaireModificationCode({ code, onEnregistre }: { code: CodePromo; onEnregistre: () => void }) {
  const { t } = useTranslation()
  const [actif, setActif] = useState(code.actif)
  const [dateFin, setDateFin] = useState<dayjs.Dayjs | null>(dayjs(code.date_fin, 'DD/MM/YYYY'))
  const [description, setDescription] = useState(code.description ?? '')
  const [erreur, setErreur] = useState<string | null>(null)

  const modifier = useMutation({
    mutationFn: () =>
      modifierUnCodePromo(code.id, {
        actif,
        date_fin: dateFin ? dateFin.format('YYYY-MM-DD') : undefined,
        description: description || undefined,
      }),
    onSuccess: onEnregistre,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text strong>{code.code}</Typography.Text>
      <Typography.Text>{t('backoffice.tarification.codesPromo.periode')}</Typography.Text>
      <DatePicker style={{ width: '100%' }} format="DD/MM/YYYY" value={dateFin} onChange={setDateFin} />
      <Typography.Text>{t('backoffice.tarification.codesPromo.description')}</Typography.Text>
      <Input.TextArea value={description} onChange={(e) => setDescription(e.target.value)} rows={2} />
      <Space>
        <Switch checked={actif} onChange={setActif} />
        <Typography.Text>{t('backoffice.tarification.codesPromo.actif')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={modifier.isPending} onClick={() => modifier.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
