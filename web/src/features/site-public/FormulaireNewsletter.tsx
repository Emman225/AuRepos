import { useMutation } from '@tanstack/react-query'
import { Button, Input, Space, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { envoyer, ErreurApi } from '../../shared/api/client'

/** Inscription à la lettre d'information (CdC § 12), pied de page du site public. */
export function FormulaireNewsletter() {
  const { t } = useTranslation()
  const [email, setEmail] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const inscrire = useMutation({
    mutationFn: () => envoyer<null>('/newsletter/abonnement', { email }, 'post'),
    onSuccess: () => setEmail(''),
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  if (inscrire.isSuccess) {
    return <Typography.Text type="success">{t('accueil.newsletter.confirmation')}</Typography.Text>
  }

  return (
    <Space orientation="vertical" size="small">
      <Typography.Text>{t('accueil.newsletter.titre')}</Typography.Text>
      <Space.Compact>
        <Input
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder={t('accueil.newsletter.placeholder')}
          style={{ width: 240 }}
        />
        <Button type="primary" loading={inscrire.isPending} disabled={!email} onClick={() => inscrire.mutate()}>
          {t('accueil.newsletter.sInscrire')}
        </Button>
      </Space.Compact>
      {erreur && (
        <Typography.Text type="danger" style={{ fontSize: 12 }}>
          {erreur}
        </Typography.Text>
      )}
    </Space>
  )
}
