import { App } from 'antd'
import { useTranslation } from 'react-i18next'

interface OptionsConfirmation {
  titre: string
  contenu?: string
  /** Action destructrice ou irréversible (suppression, refus…) : bouton rouge. */
  danger?: boolean
}

/**
 * Modale de confirmation (CdC § 6.8 : « aucune fenêtre native du navigateur », jamais
 * `window.confirm`). Rend une promesse : `true` si confirmé, `false` si annulé ou fermé.
 */
export function useConfirmerAction() {
  const { modal } = App.useApp()
  const { t } = useTranslation()

  return ({ titre, contenu, danger }: OptionsConfirmation): Promise<boolean> =>
    new Promise((resoudre) => {
      modal.confirm({
        title: titre,
        content: contenu,
        okText: t('listes.confirmation.confirmer'),
        cancelText: t('listes.confirmation.annuler'),
        okButtonProps: danger ? { danger: true } : undefined,
        onOk: () => resoudre(true),
        onCancel: () => resoudre(false),
      })
    })
}
