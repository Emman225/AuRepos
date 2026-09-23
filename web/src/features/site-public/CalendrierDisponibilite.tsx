import { LeftOutlined, RightOutlined } from '@ant-design/icons'
import { useQuery } from '@tanstack/react-query'
import { Button, Skeleton, Space, Typography } from 'antd'
import dayjs from 'dayjs'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { lire } from '../../shared/api/client'
import { couleurs } from '../../shared/theme/jetons'
import type { DisponibiliteDuMois } from './types'

interface Props {
  reference: string
}

/** Calendrier de disponibilité, mois par mois (CdC § 5.1) : rien de plus qu'occupé / libre. */
export function CalendrierDisponibilite({ reference }: Props) {
  const { t } = useTranslation()
  const [mois, setMois] = useState(() => dayjs().startOf('month'))
  const cle = mois.format('YYYY-MM')

  const disponibilite = useQuery({
    queryKey: ['disponibilite', reference, cle],
    queryFn: () => lire<DisponibiliteDuMois>(`/catalogue/logements/${reference}/disponibilite`, { mois: cle }),
  })
  const joursOccupes = new Set(disponibilite.data?.jours_occupes ?? [])

  const debutGrille = mois.startOf('week')
  const jours = Array.from({ length: 42 }, (_, i) => debutGrille.add(i, 'day'))

  return (
    <div>
      <Space style={{ marginBottom: 12 }}>
        <Button
          icon={<LeftOutlined />}
          onClick={() => setMois((m) => m.subtract(1, 'month'))}
          aria-label={t('fiche.calendrier.moisPrecedent')}
        />
        <Typography.Text strong style={{ minWidth: 140, textAlign: 'center', display: 'inline-block' }}>
          {mois.format('MMMM YYYY')}
        </Typography.Text>
        <Button
          icon={<RightOutlined />}
          onClick={() => setMois((m) => m.add(1, 'month'))}
          aria-label={t('fiche.calendrier.moisSuivant')}
        />
      </Space>

      {disponibilite.isPending ? (
        <Skeleton active />
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7, 1fr)', gap: 4 }}>
          {jours.map((jour) => {
            const horsMois = !jour.isSame(mois, 'month')
            const occupe = joursOccupes.has(jour.format('YYYY-MM-DD'))
            return (
              <div
                key={jour.format('YYYY-MM-DD')}
                title={occupe ? t('fiche.calendrier.occupe') : t('fiche.calendrier.libre')}
                style={{
                  textAlign: 'center',
                  padding: '6px 0',
                  borderRadius: 4,
                  color: horsMois ? couleurs.texteDiscret : occupe ? couleurs.blanc : couleurs.texte,
                  background: horsMois ? 'transparent' : occupe ? couleurs.erreur : couleurs.sableClair,
                  opacity: horsMois ? 0.35 : 1,
                }}
              >
                {jour.date()}
              </div>
            )
          })}
        </div>
      )}
      <Space style={{ marginTop: 8 }} size="middle">
        <Space size={6}>
          <span
            style={{ width: 12, height: 12, background: couleurs.sableClair, display: 'inline-block', borderRadius: 2 }}
          />
          <Typography.Text type="secondary">{t('fiche.calendrier.libre')}</Typography.Text>
        </Space>
        <Space size={6}>
          <span
            style={{ width: 12, height: 12, background: couleurs.erreur, display: 'inline-block', borderRadius: 2 }}
          />
          <Typography.Text type="secondary">{t('fiche.calendrier.occupe')}</Typography.Text>
        </Space>
      </Space>
    </div>
  )
}
