import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { couleurs, tonsSemantiques } from '../theme/jetons'

type Tonalite = 'neutre' | 'succes' | 'alerte' | 'erreur' | 'information'

interface Props {
  libelle: string
  valeur: string | number
  icone?: ReactNode
  /** Vers où l'indicateur mène quand on clique dessus — un chiffre affiché doit permettre une action (brief §7). Sans lien, l'indicateur est purement informatif. */
  lien?: string
  /** `grand` marque le chiffre le plus important d'un groupe (résumé du jour) ; `normal` pour les indicateurs secondaires. */
  taille?: 'grand' | 'normal'
  tonalite?: Tonalite
}

/** Un indicateur chiffré actionnable : jamais une carte isolée, toujours pensé pour être groupé (voir SectionKpi côté écrans). */
export function KPI({ libelle, valeur, icone, lien, taille = 'normal', tonalite = 'neutre' }: Props) {
  const tons = tonalite === 'neutre' ? null : tonsSemantiques[tonalite]
  const grand = taille === 'grand'

  const contenu = (
    <div
      style={{
        display: 'flex',
        alignItems: 'center',
        gap: 12,
        padding: grand ? '18px 20px' : '14px 16px',
        background: tons ? tons.fond : couleurs.blanc,
        border: `1px solid ${tons ? tons.bordure : couleurs.bordure}`,
        borderRadius: 10,
        height: '100%',
      }}
    >
      {icone && (
        <span
          style={{
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            width: grand ? 40 : 32,
            height: grand ? 40 : 32,
            borderRadius: 8,
            background: tons ? tons.bordure : couleurs.sableClair,
            color: tons ? tons.texte : couleurs.bleuNuit,
            fontSize: grand ? 20 : 16,
            flexShrink: 0,
          }}
        >
          {icone}
        </span>
      )}
      <div style={{ minWidth: 0 }}>
        <div
          style={{
            fontSize: grand ? 26 : 20,
            fontWeight: 700,
            fontVariantNumeric: 'tabular-nums',
            color: tons ? tons.texte : couleurs.texte,
            lineHeight: 1.2,
          }}
        >
          {valeur}
        </div>
        <div style={{ fontSize: 13, color: couleurs.texteDiscret, marginTop: 2 }}>{libelle}</div>
      </div>
    </div>
  )

  if (!lien) return contenu

  return (
    <Link to={lien} style={{ display: 'block', height: '100%', transition: 'transform 0.15s ease' }} className="kpi-actionnable">
      {contenu}
    </Link>
  )
}
