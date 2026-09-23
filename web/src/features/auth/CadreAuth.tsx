import { Alert, Card, Typography } from 'antd'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { LogoMarque } from '../../shared/composants/LogoMarque'
import { couleurs, ombres, rayons } from '../../shared/theme/jetons'

interface Props {
  titre: string
  sousTitre?: ReactNode
  /** Message d'échec à annoncer (role="alert"). */
  refus?: string | null
  /** Message d'information (code envoyé, mot de passe changé…). */
  information?: string | null
  children: ReactNode
  pied?: ReactNode
}

/** Cadre commun aux écrans hors session : connexion, inscription, code, mot de passe. */
export function CadreAuth({ titre, sousTitre, refus, information, children, pied }: Props) {
  return (
    <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center', padding: 16, background: couleurs.blancCasse }}>
      <Card
        style={{
          width: '100%',
          maxWidth: 460,
          borderTop: `4px solid ${couleurs.sable}`,
          borderRadius: rayons.xl,
          boxShadow: ombres.forte,
        }}
        styles={{ body: { padding: '36px 32px' } }}
      >
        <Link to="/" style={{ display: 'inline-block', marginBottom: 8 }}>
          <LogoMarque taille={76} />
        </Link>
        <Typography.Title level={2} style={{ marginTop: 16, marginBottom: 6, color: couleurs.bleuNuit }}>
          {titre}
        </Typography.Title>
        {sousTitre && (
          <Typography.Paragraph style={{ color: couleurs.texteDiscret, marginBottom: 24 }}>{sousTitre}</Typography.Paragraph>
        )}

        {information && <Alert type="success" showIcon title={information} style={{ marginBottom: 16 }} role="status" />}
        {refus && <Alert type="error" showIcon title={refus} style={{ marginBottom: 16 }} role="alert" />}

        {children}

        {pied && <div style={{ marginTop: 24, textAlign: 'center', color: couleurs.texteDiscret }}>{pied}</div>}
      </Card>
    </div>
  )
}
