import { Alert, Form, Input, Radio, Space, Typography } from 'antd'
import { Controller, type Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { formaterPrix } from '../../shared/format/devise'
import type { ConfigurationPaiement, SaisieFormulaire } from './formulaire'

interface Props {
  control: Control<SaisieFormulaire>
  configuration: ConfigurationPaiement | undefined
  /** Net à payer calculé par le serveur, pour avertir d'un dépassement du plafond. */
  netAPayer: number | undefined
  modeChoisi: SaisieFormulaire['mode_reglement']
}

/**
 * Mode de règlement (CdC § 5.2). Le paiement en ligne se coupe par un réglage,
 * qui est un simple drapeau : on peut donc désactiver le choix sans rien calculer.
 *
 * Le plafond, lui, n'est qu'un AVERTISSEMENT : le net à payer affiché ignore les
 * points de fidélité, que le serveur déduira. Désactiver le choix ici interdirait
 * un paiement que le serveur aurait accepté — c'est lui qui tranche, avec sa phrase.
 */
export function BlocReglement({ control, configuration, netAPayer, modeChoisi }: Props) {
  const { t } = useTranslation()
  const enLigneActif = configuration?.['comptant.paiement_en_ligne_actif'] ?? false
  const plafond = configuration?.['general.plafond_paiement_en_ligne'] ?? 0
  const auDessusDuPlafond = plafond > 0 && netAPayer !== undefined && netAPayer > plafond

  return (
    <Space orientation="vertical" size="middle" style={{ width: '100%' }}>
      <Controller
        control={control}
        name="mode_reglement"
        render={({ field }) => (
          <Radio.Group {...field} aria-label={t('reservation.reglement.titre')}>
            <Space orientation="vertical">
              <Radio value="en_ligne" disabled={!enLigneActif}>
                {t('reservation.reglement.enLigne')}
                {!enLigneActif && (
                  <Typography.Text type="secondary"> — {t('reservation.reglement.enLigneIndisponible')}</Typography.Text>
                )}
              </Radio>
              <Radio value="agence">{t('reservation.reglement.agence')}</Radio>
              <Radio value="a_terme">{t('reservation.reglement.aTerme')}</Radio>
            </Space>
          </Radio.Group>
        )}
      />

      {modeChoisi === 'en_ligne' && auDessusDuPlafond && (
        <Alert type="warning" showIcon title={t('reservation.reglement.plafond', { plafond: formaterPrix(plafond) })} />
      )}

      {modeChoisi === 'a_terme' && <Alert type="info" showIcon title={t('reservation.reglement.aTermeAide')} />}

      <Controller
        control={control}
        name="bon_de_commande"
        render={({ field }) => (
          <Form.Item
            label={t('reservation.reglement.bonDeCommande')}
            htmlFor="champ-bon-de-commande"
            help={t('reservation.reglement.bonDeCommandeAide')}
          >
            <Input {...field} id="champ-bon-de-commande" maxLength={100} />
          </Form.Item>
        )}
      />
    </Space>
  )
}
