import { HomeOutlined, StarFilled } from '@ant-design/icons'
import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { laiton, policeDisplay } from '../../shared/theme/jetonsSitePublic'
import type { VignetteResidence } from './types'

interface Props {
  rang: number
  residence: VignetteResidence
}

/** Une ligne d'un CLASSEMENT (les mieux notées) : le rang porte du sens ici, contrairement à une simple liste (CdC § 5.1). */
export function LigneResidenceClassee({ rang, residence }: Props) {
  const { t } = useTranslation()
  const contenu = (
    <div className="ligne-classee">
      <Typography.Text
        style={{
          gridArea: 'rang',
          fontFamily: policeDisplay,
          fontSize: 26,
          fontWeight: 500,
          color: laiton,
          alignSelf: 'center',
          justifySelf: 'center',
        }}
      >
        {rang}
      </Typography.Text>

      <div className="carte-image-forward" style={{ gridArea: 'photo', position: 'relative', borderRadius: 8, overflow: 'hidden' }}>
        {residence.photo ? (
          <img
            src={residence.photo}
            alt={residence.nom}
            loading="lazy"
            style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' }}
          />
        ) : (
          <div style={{ position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center', background: couleurs.sableClair }}>
            <HomeOutlined style={{ fontSize: 20, color: couleurs.sable }} />
          </div>
        )}
      </div>

      <div style={{ gridArea: 'nom', minWidth: 0 }}>
        <Typography.Text strong style={{ display: 'block', fontSize: 16, color: couleurs.texte }} ellipsis>
          {residence.nom}
        </Typography.Text>
        <Typography.Text style={{ display: 'block', fontSize: 13, color: couleurs.texteDiscret }} ellipsis>
          {residence.lieu.quartier}, {residence.lieu.commune}
        </Typography.Text>
      </div>

      <div style={{ gridArea: 'prix', textAlign: 'right', minWidth: 0 }}>
        {residence.note_moyenne !== null && (
          <Typography.Text strong style={{ display: 'flex', alignItems: 'center', gap: 4, justifyContent: 'flex-end', color: couleurs.texte, marginBottom: 4 }}>
            <StarFilled style={{ color: laiton, fontSize: 14 }} />
            {residence.note_moyenne}
          </Typography.Text>
        )}
        {residence.a_partir_de !== null && (
          <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret, whiteSpace: 'nowrap' }}>
            {t('accueil.residences.aPartirDe', { prix: formaterPrix(residence.a_partir_de) })}
          </Typography.Text>
        )}
      </div>
    </div>
  )

  return residence.logement_reference ? (
    <Link to={`/logements/${residence.logement_reference}`} style={{ display: 'block' }}>
      {contenu}
    </Link>
  ) : (
    contenu
  )
}
