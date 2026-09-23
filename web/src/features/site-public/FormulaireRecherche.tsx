import { useQuery } from '@tanstack/react-query'
import { Button, Checkbox, DatePicker, InputNumber, Select, Space, Typography } from 'antd'
import type { Dayjs } from 'dayjs'
import dayjs from 'dayjs'
import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { lire } from '../../shared/api/client'
import { couleurs } from '../../shared/theme/jetons'
import type { CommuneChoix, CriteresRecherche, EquipementChoix, QuartierChoix, TypeLogementChoix } from './types'

interface Props {
  filtresInitiaux: CriteresRecherche
  onRechercher: (filtres: CriteresRecherche) => void
}

/** Un champ = un libellé au-dessus, pleine largeur — la forme d'un panneau de filtres vertical, pas d'une barre de recherche horizontale (brief refonte §15). */
function Champ({ libelle, enfant }: { libelle: string; enfant: ReactNode }) {
  return (
    <div>
      <Typography.Text strong style={{ display: 'block', marginBottom: 6, fontSize: 12, color: couleurs.texteDiscret }}>
        {libelle}
      </Typography.Text>
      {enfant}
    </div>
  )
}

/** Moteur de recherche en cascade commune › quartier, dates, occupants, type, budget, équipements (CdC § 5.1). */
export function FormulaireRecherche({ filtresInitiaux, onRechercher }: Props) {
  const { t } = useTranslation()
  const [commune, setCommune] = useState<number | undefined>(filtresInitiaux.commune_id)
  const [quartier, setQuartier] = useState<number | undefined>(filtresInitiaux.quartier_id)
  const [dates, setDates] = useState<[Dayjs, Dayjs] | null>(
    filtresInitiaux.arrivee && filtresInitiaux.depart
      ? [dayjs(filtresInitiaux.arrivee), dayjs(filtresInitiaux.depart)]
      : null,
  )
  const [adultes, setAdultes] = useState<number | undefined>(filtresInitiaux.adultes)
  const [enfants, setEnfants] = useState<number | undefined>(filtresInitiaux.enfants)
  const [type, setType] = useState<number | undefined>(filtresInitiaux.type_logement_id)
  const [budget, setBudget] = useState<number | undefined>(filtresInitiaux.budget_max)
  const [equipements, setEquipements] = useState<number[]>(filtresInitiaux.equipements ?? [])

  const communes = useQuery({
    queryKey: ['referentiels', 'communes'],
    queryFn: () => lire<CommuneChoix[]>('/referentiels/communes'),
  })
  const quartiers = useQuery({
    queryKey: ['referentiels', 'quartiers', commune],
    queryFn: () => lire<QuartierChoix[]>('/referentiels/quartiers', { commune_id: commune }),
    enabled: commune !== undefined,
  })
  const types = useQuery({
    queryKey: ['referentiels', 'types-logement'],
    queryFn: () => lire<TypeLogementChoix[]>('/referentiels/types-logement'),
  })
  const equipementsDisponibles = useQuery({
    queryKey: ['referentiels', 'equipements'],
    queryFn: () => lire<EquipementChoix[]>('/referentiels/equipements'),
  })
  const filtresEquipements = (equipementsDisponibles.data ?? []).filter((e) => e.filtre_recherche)

  const soumettre = (): void => {
    onRechercher({
      commune_id: commune,
      quartier_id: quartier,
      arrivee: dates?.[0].format('YYYY-MM-DD'),
      depart: dates?.[1].format('YYYY-MM-DD'),
      adultes,
      enfants,
      type_logement_id: type,
      budget_max: budget,
      equipements: equipements.length > 0 ? equipements : undefined,
    })
  }

  const reinitialiser = (): void => {
    setCommune(undefined)
    setQuartier(undefined)
    setDates(null)
    setAdultes(undefined)
    setEnfants(undefined)
    setType(undefined)
    setBudget(undefined)
    setEquipements([])
    onRechercher({})
  }

  return (
    <Space orientation="vertical" size={18} style={{ width: '100%' }}>
      <Champ
        libelle={t('recherche.filtre.commune')}
        enfant={
          <Select
            allowClear
            aria-label={t('recherche.filtre.commune')}
            style={{ width: '100%' }}
            placeholder={t('recherche.filtre.toutesLesCommunes')}
            value={commune}
            onChange={(v) => {
              setCommune(v)
              setQuartier(undefined) // un quartier n'appartient qu'à une seule commune
            }}
            loading={communes.isPending}
            options={(communes.data ?? []).map((c) => ({ value: c.id, label: c.nom }))}
          />
        }
      />

      <Champ
        libelle={t('recherche.filtre.quartier')}
        enfant={
          <Select
            allowClear
            aria-label={t('recherche.filtre.quartier')}
            style={{ width: '100%' }}
            placeholder={t('recherche.filtre.tousLesQuartiers')}
            value={quartier}
            onChange={setQuartier}
            disabled={commune === undefined}
            loading={quartiers.isFetching}
            options={(quartiers.data ?? []).map((q) => ({ value: q.id, label: q.nom }))}
          />
        }
      />

      <Champ
        libelle={`${t('recherche.filtre.arrivee')} — ${t('recherche.filtre.depart')}`}
        enfant={
          <DatePicker.RangePicker
            style={{ width: '100%' }}
            value={dates}
            onChange={(v) => setDates(v && v[0] && v[1] ? [v[0], v[1]] : null)}
            disabledDate={(d) => d.isBefore(dayjs().startOf('day'))}
            placeholder={[t('recherche.filtre.arrivee'), t('recherche.filtre.depart')]}
          />
        }
      />

      <div style={{ display: 'flex', gap: 12 }}>
        <div style={{ flex: 1 }}>
          <Champ
            libelle={t('recherche.filtre.adultes')}
            enfant={
              <InputNumber
                aria-label={t('recherche.filtre.adultes')}
                style={{ width: '100%' }}
                min={1}
                max={60}
                value={adultes}
                onChange={(v) => setAdultes(v ?? undefined)}
              />
            }
          />
        </div>
        <div style={{ flex: 1 }}>
          <Champ
            libelle={t('recherche.filtre.enfants')}
            enfant={
              <InputNumber
                aria-label={t('recherche.filtre.enfants')}
                style={{ width: '100%' }}
                min={0}
                max={60}
                value={enfants}
                onChange={(v) => setEnfants(v ?? undefined)}
              />
            }
          />
        </div>
      </div>

      <Champ
        libelle={t('recherche.filtre.type')}
        enfant={
          <Select
            allowClear
            aria-label={t('recherche.filtre.type')}
            style={{ width: '100%' }}
            placeholder={t('recherche.filtre.tousLesTypes')}
            value={type}
            onChange={setType}
            loading={types.isPending}
            options={(types.data ?? []).map((typeLogement) => ({ value: typeLogement.id, label: typeLogement.nom }))}
          />
        }
      />

      <Champ
        libelle={t('recherche.filtre.budget')}
        enfant={
          <InputNumber
            aria-label={t('recherche.filtre.budget')}
            style={{ width: '100%' }}
            min={1}
            value={budget}
            onChange={(v) => setBudget(v ?? undefined)}
          />
        }
      />

      {filtresEquipements.length > 0 && (
        <Champ
          libelle={t('recherche.filtre.equipements')}
          enfant={
            <Checkbox.Group
              value={equipements}
              onChange={(v) => setEquipements(v as number[])}
              options={filtresEquipements.map((e) => ({ value: e.id, label: e.nom }))}
              style={{ display: 'flex', flexDirection: 'column', gap: 8 }}
            />
          }
        />
      )}

      <Space orientation="vertical" style={{ width: '100%' }}>
        <Button type="primary" block onClick={soumettre}>
          {t('recherche.filtre.rechercher')}
        </Button>
        <Button block onClick={reinitialiser}>
          {t('recherche.filtre.reinitialiser')}
        </Button>
      </Space>
    </Space>
  )
}
