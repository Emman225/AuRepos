import { HomeOutlined, StarFilled, TeamOutlined } from '@ant-design/icons'
import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { laitonClair } from '../../shared/theme/jetonsSitePublic'
import type { VignetteLogement } from './types'

interface Props {
  logement: VignetteLogement
}

/** Vignette d'un logement : la photo EST la carte, la légende se lit dessus (CdC § 5.1, 5.2). */
export function CarteLogement({ logement }: Props) {
  const { t } = useTranslation()

  return (
    <Link
      to={`/logements/${logement.reference}`}
      className="carte-image-forward"
      style={{
        display: 'block',
        position: 'relative',
        borderRadius: 10,
        overflow: 'hidden',
        aspectRatio: '4 / 5',
        boxShadow: '0 10px 26px rgba(21, 43, 71, 0.14)',
      }}
    >
      {logement.photo ? (
        <img
          src={logement.photo}
          alt={logement.nom}
          loading="lazy"
          style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }}
        />
      ) : (
        <div
          style={{
            position: 'absolute',
            inset: 0,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: couleurs.sableClair,
          }}
        >
          <HomeOutlined style={{ fontSize: 36, color: couleurs.sable }} />
        </div>
      )}

      <div
        style={{
          position: 'absolute',
          inset: 0,
          background: 'linear-gradient(0deg, rgba(21,43,71,0.92) 0%, rgba(21,43,71,0.55) 34%, rgba(21,43,71,0) 62%)',
        }}
      />

      {logement.note_moyenne !== null && (
        <div
          style={{
            position: 'absolute',
            top: 12,
            right: 12,
            display: 'flex',
            alignItems: 'center',
            gap: 4,
            background: 'rgba(255,255,255,0.94)',
            borderRadius: 5,
            padding: '3px 8px',
            fontSize: 12,
            fontWeight: 600,
            color: couleurs.bleuNuit,
          }}
        >
          <StarFilled style={{ color: laitonClair, fontSize: 11 }} />
          {logement.note_moyenne}
        </div>
      )}

      <div style={{ position: 'absolute', left: 16, right: 16, bottom: 14 }}>
        <Typography.Text strong style={{ display: 'block', color: couleurs.blanc, fontSize: 16 }}>
          {logement.nom}
        </Typography.Text>
        <Typography.Text
          style={{ display: 'flex', alignItems: 'center', gap: 8, color: 'rgba(255,255,255,0.8)', fontSize: 13, marginBottom: 8 }}
        >
          {logement.lieu.quartier}, {logement.lieu.commune}
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3, whiteSpace: 'nowrap' }}>
            <TeamOutlined style={{ fontSize: 11 }} /> {logement.capacite_maximale}
          </span>
        </Typography.Text>
        {logement.prix_par_nuit !== null && (
          <Typography.Text strong style={{ color: couleurs.blanc, fontSize: 15 }}>
            {t('recherche.prixParNuit', { prix: formaterPrix(logement.prix_par_nuit) })}
          </Typography.Text>
        )}
      </div>
    </Link>
  )
}
