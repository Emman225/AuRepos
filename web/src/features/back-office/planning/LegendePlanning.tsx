import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { couleurs, etatsPlanning } from '../../../shared/theme/jetons'

/**
 * Légende des huit états du planning (CdC § 6.2, P2-BO-01). Source de couleur UNIQUE :
 * `etatsPlanning` (shared/theme/jetons.ts) — jamais une couleur inventée ici, jamais dupliquée
 * dans une table locale. `libelle` y est déjà en français fixe (comme le reste de la charte
 * graphique, `couleurs`/`typographie`…) : pas de clé i18n pour ce texte-là non plus.
 */
export function LegendePlanning() {
  const { t } = useTranslation()

  return (
    <div
      style={{
        display: 'flex',
        flexWrap: 'wrap',
        gap: 14,
        alignItems: 'center',
        padding: '10px 14px',
        marginBottom: 16,
        background: couleurs.blanc,
        border: `1px solid ${couleurs.bordure}`,
        borderRadius: 10,
      }}
    >
      <Typography.Text strong style={{ fontSize: 12, color: couleurs.texteDiscret }}>
        {t('backoffice.planning.legende.titre')}
      </Typography.Text>
      {Object.entries(etatsPlanning).map(([cle, etat]) => (
        <span key={cle} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
          <span
            aria-hidden
            style={{
              width: 14,
              height: 14,
              borderRadius: 4,
              background: etat.fond,
              border: `1px solid ${cle === 'libre' ? couleurs.bordure : etat.fond}`,
              flexShrink: 0,
            }}
          />
          <Typography.Text style={{ fontSize: 12, color: couleurs.texte }}>{etat.libelle}</Typography.Text>
        </span>
      ))}
    </div>
  )
}
