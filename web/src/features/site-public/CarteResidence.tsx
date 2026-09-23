import { HomeOutlined } from '@ant-design/icons'
import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import type { VignetteResidence } from './types'

interface Props {
  residence: VignetteResidence
}

/** Vignette large format d'une résidence mise en avant — le format « film », pas une carte de liste (CdC § 5.1). */
export function CarteResidence({ residence }: Props) {
  const { t } = useTranslation()
  const contenu = (
    <div
      className="carte-image-forward"
      style={{
        position: 'relative',
        borderRadius: 10,
        overflow: 'hidden',
        aspectRatio: '16 / 11',
        boxShadow: '0 10px 26px rgba(21, 43, 71, 0.14)',
      }}
    >
      {residence.photo ? (
        <img
          src={residence.photo}
          alt={residence.nom}
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
          background: 'linear-gradient(0deg, rgba(21,43,71,0.9) 0%, rgba(21,43,71,0.4) 40%, rgba(21,43,71,0) 65%)',
        }}
      />
      <div style={{ position: 'absolute', left: 18, right: 18, bottom: 16 }}>
        <Typography.Text strong style={{ display: 'block', color: couleurs.blanc, fontSize: 17 }}>
          {residence.nom}
        </Typography.Text>
        <Typography.Text style={{ display: 'block', color: 'rgba(255,255,255,0.8)', fontSize: 13, marginBottom: 6 }}>
          {residence.lieu.quartier}, {residence.lieu.commune}
        </Typography.Text>
        {residence.a_partir_de !== null && (
          <Typography.Text strong style={{ color: couleurs.blanc, fontSize: 14 }}>
            {t('accueil.residences.aPartirDe', { prix: formaterPrix(residence.a_partir_de) })}
          </Typography.Text>
        )}
      </div>
    </div>
  )

  return residence.logement_reference ? <Link to={`/logements/${residence.logement_reference}`}>{contenu}</Link> : contenu
}
