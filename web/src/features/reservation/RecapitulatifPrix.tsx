import { Divider, Row, Typography } from 'antd'
import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import type { DevisDeSejour } from './types'

function Ligne({ libelle, montant, fort }: { libelle: ReactNode; montant: number; fort?: boolean }) {
  return (
    <Row justify="space-between" style={{ gap: 16, marginBottom: 6 }}>
      <Typography.Text strong={fort} style={{ color: fort ? couleurs.texte : couleurs.texteDiscret }}>
        {libelle}
      </Typography.Text>
      <Typography.Text strong={fort} style={{ whiteSpace: 'nowrap' }}>
        {formaterPrix(montant)}
      </Typography.Text>
    </Row>
  )
}

/**
 * Le détail du prix tel que le SERVEUR l'a calculé (CalculSejourService, P1-TAR-07).
 * Rien n'est additionné ici : chaque ligne est un montant reçu tel quel. C'est le
 * même objet qui sera figé sur le séjour à la réservation (CdC § 5.4).
 */
export function RecapitulatifPrix({ devis }: { devis: DevisDeSejour }) {
  const { t } = useTranslation()

  return (
    <div>
      <Ligne
        libelle={t('reservation.recapitulatif.hebergement', { count: devis.nombre_de_nuits })}
        montant={devis.hebergement_brut_ht}
      />

      {devis.supplements.map((supplement) => (
        <Ligne
          key={supplement.code}
          libelle={supplement.quantite > 1 ? `${supplement.libelle} × ${supplement.quantite}` : supplement.libelle}
          montant={supplement.montant}
        />
      ))}

      {devis.remise_ht > 0 && (
        <Ligne
          libelle={t('reservation.recapitulatif.remise', { pourcentage: devis.remise_pourcentage })}
          montant={-devis.remise_ht}
        />
      )}

      {devis.reductions.map((reduction) => (
        <Ligne key={reduction.libelle} libelle={reduction.libelle} montant={-reduction.montant} />
      ))}

      {devis.extras.map((extra) => (
        <Ligne key={extra.libelle} libelle={extra.libelle} montant={extra.montant_ht} />
      ))}

      {devis.transfert_ht > 0 && <Ligne libelle={t('reservation.recapitulatif.transfert')} montant={devis.transfert_ht} />}

      <Divider style={{ margin: '10px 0' }} />

      <Ligne libelle={t('reservation.recapitulatif.totalHt')} montant={devis.total_ht} />
      {devis.total_tva > 0 && <Ligne libelle={t('reservation.recapitulatif.tva')} montant={devis.total_tva} />}
      {devis.tdt > 0 && <Ligne libelle={t('reservation.recapitulatif.tdt')} montant={devis.tdt} />}
      {devis.taxe_de_sejour > 0 && (
        <Ligne
          libelle={t('reservation.recapitulatif.taxeDeSejour', { count: devis.occupants_taxables })}
          montant={devis.taxe_de_sejour}
        />
      )}

      <Divider style={{ margin: '10px 0' }} />

      <Ligne libelle={t('reservation.recapitulatif.netAPayer')} montant={devis.net_a_payer} fort />

      {devis.caution > 0 && (
        <>
          <Ligne libelle={t('reservation.recapitulatif.caution')} montant={devis.caution} />
          <Ligne libelle={t('reservation.recapitulatif.totalAvecCaution')} montant={devis.total_avec_caution} fort />
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
            {t('reservation.recapitulatif.cautionRendue')}
          </Typography.Text>
        </>
      )}
    </div>
  )
}
