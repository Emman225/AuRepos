import { Typography } from 'antd'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Link } from 'react-router-dom'
import { couleurs } from '../../shared/theme/jetons'
import { laiton } from '../../shared/theme/jetonsSitePublic'
import type { TypeLogementChoix } from './types'

interface Props {
  types: TypeLogementChoix[]
}

/** Traits fins (1.3px), dessinés pour ce projet plutôt que des glyphes génériques de bibliothèque. */
function IconeChambre() {
  return (
    <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
      <path d="M4 12V21H22V12" stroke="currentColor" strokeWidth="1.3" />
      <path d="M2 13L13 4L24 13" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
      <rect x="9" y="15" width="8" height="6" stroke="currentColor" strokeWidth="1.1" />
    </svg>
  )
}
function IconeStudio() {
  return (
    <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
      <rect x="3.5" y="6" width="19" height="15" stroke="currentColor" strokeWidth="1.3" />
      <path d="M3.5 12.5H22.5" stroke="currentColor" strokeWidth="1.1" />
      <path d="M9 21V12.5" stroke="currentColor" strokeWidth="1.1" />
      <circle cx="14.5" cy="17.2" r="1.6" stroke="currentColor" strokeWidth="1.1" />
    </svg>
  )
}
function IconeAppartement() {
  return (
    <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
      <rect x="5" y="3.5" width="16" height="19" stroke="currentColor" strokeWidth="1.3" />
      <path d="M9 3.5V22" stroke="currentColor" strokeWidth="1" opacity="0.5" />
      <path d="M17 3.5V22" stroke="currentColor" strokeWidth="1" opacity="0.5" />
      <rect x="6.3" y="7" width="1.8" height="1.8" fill="currentColor" opacity="0.7" />
      <rect x="11.1" y="7" width="1.8" height="1.8" fill="currentColor" opacity="0.7" />
      <rect x="18" y="7" width="1.8" height="1.8" fill="currentColor" opacity="0.7" />
      <rect x="6.3" y="12" width="1.8" height="1.8" fill="currentColor" opacity="0.7" />
      <rect x="18" y="12" width="1.8" height="1.8" fill="currentColor" opacity="0.7" />
    </svg>
  )
}
function IconeDuplex() {
  return (
    <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
      <path d="M3 13L13 5L23 13" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" />
      <path d="M5.5 11V21.5H20.5V11" stroke="currentColor" strokeWidth="1.3" />
      <path d="M5.5 16H20.5" stroke="currentColor" strokeWidth="1" opacity="0.6" />
      <rect x="11" y="17.3" width="4" height="4.2" stroke="currentColor" strokeWidth="1.1" />
    </svg>
  )
}
function IconeVilla() {
  return (
    <svg width="26" height="26" viewBox="0 0 26 26" fill="none">
      <path d="M2 12.5L9 6L13 9.5L18 5L24 12.5" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M5.5 10.5V21H21.5V10.5" stroke="currentColor" strokeWidth="1.3" />
      <path d="M11 21V14.5H16V21" stroke="currentColor" strokeWidth="1.1" />
    </svg>
  )
}

const ICONES_PAR_CODE: Record<string, ReactNode> = {
  chambre: <IconeChambre />,
  studio: <IconeStudio />,
  f2: <IconeAppartement />,
  f3: <IconeAppartement />,
  f4: <IconeAppartement />,
  f5: <IconeAppartement />,
  duplex: <IconeDuplex />,
  villa: <IconeVilla />,
}

/** Catégories de logement (CdC § 5.1 : studio, appartement, villa, chambre), vers la recherche filtrée (P1-PUB-02). */
export function SectionCategories({ types }: Props) {
  const { t } = useTranslation()
  if (types.length === 0) return null

  return (
    <section>
      <Typography.Title level={2}>{t('accueil.categories.titre')}</Typography.Title>
      <div
        style={{
          display: 'flex',
          gap: 14,
          overflowX: 'auto',
          paddingBottom: 8,
          paddingTop: 4,
        }}
      >
        {types.map((type) => (
          <Link
            key={type.id}
            to={`/recherche?type_logement_id=${type.id}`}
            className="categorie-pastille"
            style={{
              display: 'flex',
              flexDirection: 'column',
              alignItems: 'center',
              gap: 14,
              flex: '0 0 124px',
              padding: '26px 10px 20px',
              borderRadius: 14,
              textAlign: 'center',
              border: `1px solid ${couleurs.bordure}`,
              background: couleurs.blanc,
            }}
          >
            <span
              style={{
                width: 52,
                height: 52,
                borderRadius: '50%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: couleurs.bleuNuit,
                background: couleurs.sableClair,
              }}
            >
              {ICONES_PAR_CODE[type.code] ?? <IconeAppartement />}
            </span>
            <Typography.Text style={{ fontSize: 13, color: couleurs.texte, fontWeight: 600 }}>{type.nom}</Typography.Text>
            <span style={{ fontSize: 11, color: laiton, letterSpacing: '0.02em' }}>{t('accueil.categories.voir')}</span>
          </Link>
        ))}
      </div>
    </section>
  )
}
