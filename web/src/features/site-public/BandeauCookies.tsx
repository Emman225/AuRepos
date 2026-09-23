import { Button, Space, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { couleurs } from '../../shared/theme/jetons'

const CLE_COOKIES = 'residences.cookies'
type ChoixCookies = 'accepte' | 'refuse'

function choixStocke(): ChoixCookies | null {
  try {
    const valeur = localStorage.getItem(CLE_COOKIES)
    return valeur === 'accepte' || valeur === 'refuse' ? valeur : null
  } catch {
    return null // navigation privée stricte : le bandeau se réaffiche à chaque visite, sans planter
  }
}

function memoriserChoix(choix: ChoixCookies): void {
  try {
    localStorage.setItem(CLE_COOKIES, choix)
  } catch {
    /* stockage indisponible : le choix vivra le temps de l'onglet */
  }
}

/** Gestion des cookies (CdC § 5.1) : consentement mémorisé, aucun cookie non essentiel avant ce choix. */
export function BandeauCookies() {
  const { t } = useTranslation()
  const [choix, setChoix] = useState<ChoixCookies | null>(choixStocke)
  if (choix !== null) return null

  const decider = (c: ChoixCookies): void => {
    memoriserChoix(c)
    setChoix(c)
  }

  return (
    <div
      style={{
        position: 'fixed',
        insetInline: 0,
        insetBlockEnd: 0,
        background: couleurs.bleuNuit,
        padding: '16px 24px',
        zIndex: 1000,
      }}
    >
      <Space style={{ width: '100%', justifyContent: 'space-between', flexWrap: 'wrap', rowGap: 12 }}>
        <Typography.Text style={{ color: couleurs.blancCasse, maxWidth: 640 }}>
          {t('accueil.cookies.message')}
        </Typography.Text>
        <Space>
          <Button onClick={() => decider('refuse')}>{t('accueil.cookies.refuser')}</Button>
          <Button type="primary" onClick={() => decider('accepte')}>
            {t('accueil.cookies.accepter')}
          </Button>
        </Space>
      </Space>
    </div>
  )
}
