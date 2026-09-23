import { Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import type { DevisDeSejour } from './types'

interface Props {
  devis: DevisDeSejour
}

function Ligne({ libelle, montant }: { libelle: string; montant: number }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between', padding: '4px 0' }}>
      <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret }}>{libelle}</Typography.Text>
      <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret, fontVariantNumeric: 'tabular-nums' }}>
        {formaterPrix(montant)}
      </Typography.Text>
    </div>
  )
}

/**
 * Détail d'un devis figé : mêmes montants qu'une réservation (CdC § 5.2, 5.4).
 * Hiérarchie voulue (brief refonte §39) : le détail reste discret, le net à payer
 * domine visuellement, et la caution est isolée dans son propre bloc — ce n'est
 * pas un produit, elle ne doit jamais se lire comme une ligne parmi d'autres.
 */
export function ResumeDevis({ devis }: Props) {
  const { t } = useTranslation()

  return (
    <div style={{ background: couleurs.blanc, border: `1px solid ${couleurs.bordure}`, borderRadius: 10, padding: 20 }}>
      <Space orientation="vertical" style={{ width: '100%' }} size={0}>
        <Ligne
          libelle={t('tunnel.devis.hebergement', { count: devis.nombre_de_nuits })}
          montant={devis.hebergement_brut_ht}
        />
        {devis.reductions.map((r) => (
          <Ligne key={r.libelle} libelle={r.libelle} montant={-r.montant} />
        ))}
        {devis.remise_ht > 0 && (
          <Ligne
            libelle={t('tunnel.devis.remise', { pourcentage: devis.remise_pourcentage })}
            montant={-devis.remise_ht}
          />
        )}
        <Ligne libelle={t('tunnel.devis.tva')} montant={devis.tva_hebergement_et_extras} />
        <Ligne libelle={t('tunnel.devis.autresTaxes')} montant={devis.autres_taxes} />
      </Space>

      <div style={{ borderTop: `1px solid ${couleurs.bordure}`, margin: '12px 0 10px' }} />

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline' }}>
        <Typography.Text strong style={{ fontSize: 14 }}>
          {t('tunnel.devis.netAPayer')}
        </Typography.Text>
        <Typography.Text strong style={{ fontSize: 24, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
          {formaterPrix(devis.net_a_payer)}
        </Typography.Text>
      </div>

      <div style={{ marginTop: 16, padding: '12px 14px', background: couleurs.sableClair, border: `1px dashed ${couleurs.sable}`, borderRadius: 8 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between' }}>
          <Typography.Text strong style={{ fontSize: 13 }}>
            {t('tunnel.devis.caution')}
          </Typography.Text>
          <Typography.Text strong style={{ fontSize: 15, fontVariantNumeric: 'tabular-nums' }}>
            {formaterPrix(devis.caution)}
          </Typography.Text>
        </div>
        <Typography.Text style={{ fontSize: 12, color: couleurs.texteDiscret }}>{t('tunnel.devis.cautionNote')}</Typography.Text>
      </div>
    </div>
  )
}
