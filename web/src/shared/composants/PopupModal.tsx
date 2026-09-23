import { Modal as ModalAntd } from 'antd'
import type { ModalProps } from 'antd'

/**
 * Toute fenêtre modale du back office se ferme par un geste EXPLICITE (bouton « Fermer »
 * ou « Annuler »), jamais par un clic à côté ou la touche Échap — une saisie en cours ne
 * doit jamais se perdre par mégarde. `maskClosable`/`keyboard` restent overridables au cas
 * par cas (aucun ne l'est aujourd'hui), mais leur valeur par défaut passe ici, une seule fois,
 * plutôt que d'être répétée sur chaque modale de l'application.
 */
export function Modal(props: ModalProps) {
  return <ModalAntd maskClosable={false} keyboard={false} {...props} />
}
