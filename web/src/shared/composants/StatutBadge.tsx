import { tonsSemantiques } from '../theme/jetons'
import { tonaliteDuStatut, type DomaineStatut } from '../theme/statuts'

interface Props {
  domaine: DomaineStatut
  code: string
  libelle: string
}

/** Un statut = un point de couleur + un libellé, jamais la couleur seule (accessibilité, §30). */
export function StatutBadge({ domaine, code, libelle }: Props) {
  const tons = tonsSemantiques[tonaliteDuStatut(domaine, code)]

  return (
    <span
      style={{
        display: 'inline-flex',
        alignItems: 'center',
        gap: 6,
        padding: '3px 10px',
        borderRadius: 999,
        background: tons.fond,
        border: `1px solid ${tons.bordure}`,
        color: tons.texte,
        fontSize: 12,
        fontWeight: 600,
        lineHeight: 1.4,
        whiteSpace: 'nowrap',
      }}
    >
      <span style={{ width: 6, height: 6, borderRadius: '50%', background: tons.texte, flexShrink: 0 }} />
      {libelle}
    </span>
  )
}
