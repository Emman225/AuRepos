import { useQuery } from '@tanstack/react-query'
import { Button, Result, Skeleton } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { formaterPrix } from '../../shared/format/devise'
import { lireEtatPaiement } from './api'

const RAFRAICHISSEMENT_MS = 3000

/**
 * Retour de la passerelle (CdC § 5.2). On ne croit jamais le navigateur ni le contenu d'un
 * rappel : l'état affiché vient d'une vérification serveur à serveur, revérifiée en boucle
 * tant que le paiement n'est pas conclu (le rappel de la passerelle peut arriver après nous).
 */
export function PagePaiementRetour() {
  const { t } = useTranslation()
  const { reference } = useParams<{ reference: string }>()

  const paiement = useQuery({
    queryKey: ['paiement', reference],
    queryFn: () => lireEtatPaiement(reference ?? ''),
    enabled: !!reference,
    refetchInterval: (query) => (query.state.data?.etat === 'en_attente' ? RAFRAICHISSEMENT_MS : false),
  })

  if (paiement.isPending) {
    return (
      <div style={{ padding: '48px 24px', maxWidth: 700, margin: '0 auto' }}>
        <Skeleton active />
      </div>
    )
  }

  if (paiement.isError || !paiement.data) {
    return (
      <div style={{ padding: '48px 24px', maxWidth: 700, margin: '0 auto' }}>
        <Result status="error" title={t('tunnel.paiement.erreur')} />
      </div>
    )
  }

  const { etat, montant, sejour, motif } = paiement.data

  return (
    <div style={{ padding: '48px 24px', maxWidth: 700, margin: '0 auto' }}>
      <Result
        status={etat === 'reussi' ? 'success' : etat === 'echoue' || etat === 'expire' ? 'error' : 'info'}
        title={t(`tunnel.paiement.etat.${etat}`)}
        subTitle={
          etat === 'reussi'
            ? t('tunnel.paiement.montantRegle', { montant: formaterPrix(montant), sejour })
            : (motif ?? undefined)
        }
        extra={
          <Link to="/">
            <Button type="primary">{t('tunnel.paiement.retourAuSite')}</Button>
          </Link>
        }
      />
    </div>
  )
}
