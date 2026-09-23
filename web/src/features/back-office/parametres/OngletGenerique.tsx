import { useMutation } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Select, Space, Switch, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { useSession } from '../../auth/session'
import { enregistrerUnOnglet } from './api'
import type { AdministrateurChoix, Onglet, ParametreValeur } from './types'

interface Props {
  onglet: Onglet
  administrateurs: AdministrateurChoix[]
  onEnregistre: (onglets: Onglet[]) => void
}

/** Un onglet de Paramètres (CdC § 12) : ses champs sont décrits par l'API, pas codés en dur ici. */
export function OngletGenerique({ onglet, administrateurs, onEnregistre }: Props) {
  const { t } = useTranslation()
  const profil = useSession((s) => s.utilisateur?.profil)
  const [valeurs, setValeurs] = useState<Record<string, unknown>>(() =>
    Object.fromEntries(onglet.parametres.map((p) => [p.nom, p.valeur])),
  )
  const [erreur, setErreur] = useState<string | null>(null)

  const enregistrer = useMutation({
    mutationFn: () => {
      const saisie: Record<string, unknown> = {}
      for (const p of onglet.parametres) {
        // Un réglage à double validation ne se change jamais depuis cet écran (CdC § 7.3),
        // et un réglage réservé qu'on ne peut pas modifier ne doit pas non plus repartir
        // inchangé dans la requête : le serveur le refuserait quand même.
        if (p.double_validation) continue
        if (p.reserve_super_administrateur && profil !== 'super_administrateur') continue
        saisie[p.nom] = valeurs[p.nom]
      }
      return enregistrerUnOnglet(onglet.code, saisie)
    },
    onSuccess: (reponse) => onEnregistre(reponse.onglets),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" size="large" style={{ width: '100%', maxWidth: 720 }}>
      {onglet.parametres.map((p) => (
        <ChampParametre
          key={p.cle}
          parametre={p}
          valeur={valeurs[p.nom]}
          onChange={(v) => setValeurs((prev) => ({ ...prev, [p.nom]: v }))}
          administrateurs={administrateurs}
          desactive={p.double_validation || (p.reserve_super_administrateur && profil !== 'super_administrateur')}
        />
      ))}
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} onClick={() => enregistrer.mutate()}>
        {t('backoffice.parametres.enregistrer')}
      </Button>
    </Space>
  )
}

function ChampParametre({
  parametre,
  valeur,
  onChange,
  administrateurs,
  desactive,
}: {
  parametre: ParametreValeur
  valeur: unknown
  onChange: (v: unknown) => void
  administrateurs: AdministrateurChoix[]
  desactive: boolean
}) {
  const { t } = useTranslation()

  return (
    <div>
      <Typography.Text strong>{parametre.libelle}</Typography.Text>
      {parametre.double_validation && (
        <div>
          <Typography.Text type="secondary">{t('backoffice.parametres.doubleValidation')}</Typography.Text>
        </div>
      )}
      {parametre.reserve_super_administrateur && (
        <div>
          <Typography.Text type="secondary">{t('backoffice.parametres.reserveSuperAdmin')}</Typography.Text>
        </div>
      )}
      <div style={{ marginTop: 4 }}>
        <Champ parametre={parametre} valeur={valeur} onChange={onChange} administrateurs={administrateurs} desactive={desactive} />
      </div>
      {parametre.aide && (
        <Typography.Text type="secondary" style={{ fontSize: 12 }}>
          {parametre.aide}
        </Typography.Text>
      )}
    </div>
  )
}

function Champ({
  parametre,
  valeur,
  onChange,
  administrateurs,
  desactive,
}: {
  parametre: ParametreValeur
  valeur: unknown
  onChange: (v: unknown) => void
  administrateurs: AdministrateurChoix[]
  desactive: boolean
}) {
  switch (parametre.type) {
    case 'booleen':
      return <Switch checked={Boolean(valeur)} onChange={onChange} disabled={desactive} />
    case 'entier':
      return (
        <InputNumber
          style={{ width: 240 }}
          value={valeur as number | null}
          onChange={onChange}
          disabled={desactive}
          precision={0}
        />
      )
    case 'decimal':
      return (
        <InputNumber style={{ width: 240 }} value={valeur as number | null} onChange={onChange} disabled={desactive} step={0.1} />
      )
    case 'heure':
      return (
        <input
          type="time"
          value={(valeur as string | null) ?? ''}
          onChange={(e) => onChange(e.target.value)}
          disabled={desactive}
        />
      )
    case 'liste':
      return (
        <Select
          style={{ width: 320 }}
          value={valeur as string}
          onChange={onChange}
          disabled={desactive}
          options={Object.entries(parametre.options ?? {}).map(([value, label]) => ({ value, label }))}
        />
      )
    case 'administrateur':
      return (
        <Select
          style={{ width: 320 }}
          allowClear
          value={valeur as number | undefined}
          onChange={onChange}
          disabled={desactive}
          options={administrateurs.map((a) => ({ value: a.id, label: a.nom }))}
        />
      )
    case 'texte_long':
      return (
        <Input.TextArea
          rows={4}
          value={(valeur as string | null) ?? ''}
          onChange={(e) => onChange(e.target.value)}
          disabled={desactive}
        />
      )
    default:
      return (
        <Input
          style={{ maxWidth: 480 }}
          value={(valeur as string | null) ?? ''}
          onChange={(e) => onChange(e.target.value)}
          disabled={desactive}
        />
      )
  }
}
