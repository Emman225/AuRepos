import { Button, Space, Typography } from 'antd'
import { useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { couleurs } from '../theme/jetons'

interface Props {
  /** Rendue avec la signature en base64 (`canvas.toDataURL()`) au clic sur « Valider ». */
  onValider: (signatureBase64: string) => void
  enCours?: boolean
}

/**
 * Signature capturée à l'écran (état des lieux, CdC § 6.1) : un `<canvas>` minimal, dessiné au
 * doigt ou à la souris. Aucun composant de signature n'existait déjà dans ce dépôt — volontairement
 * simple (pas de lissage, pas de pression), la signature n'a qu'une valeur de preuve, pas d'usage graphique.
 */
export function SignaturePad({ onValider, enCours }: Props) {
  const { t } = useTranslation()
  const toileRef = useRef<HTMLCanvasElement>(null)
  const enTrain = useRef(false)
  const [aDessine, setADessine] = useState(false)

  const position = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const rect = e.currentTarget.getBoundingClientRect()
    return { x: e.clientX - rect.left, y: e.clientY - rect.top }
  }

  const demarrer = (e: React.PointerEvent<HTMLCanvasElement>) => {
    const ctx = toileRef.current?.getContext('2d')
    if (!ctx) return
    enTrain.current = true
    const { x, y } = position(e)
    ctx.beginPath()
    ctx.moveTo(x, y)
  }

  const dessiner = (e: React.PointerEvent<HTMLCanvasElement>) => {
    if (!enTrain.current) return
    const ctx = toileRef.current?.getContext('2d')
    if (!ctx) return
    const { x, y } = position(e)
    ctx.lineWidth = 2
    ctx.lineCap = 'round'
    ctx.strokeStyle = couleurs.texte
    ctx.lineTo(x, y)
    ctx.stroke()
    setADessine(true)
  }

  const arreter = () => {
    enTrain.current = false
  }

  const effacer = () => {
    const toile = toileRef.current
    const ctx = toile?.getContext('2d')
    if (!toile || !ctx) return
    ctx.clearRect(0, 0, toile.width, toile.height)
    setADessine(false)
  }

  return (
    <div>
      <canvas
        ref={toileRef}
        width={440}
        height={160}
        role="img"
        aria-label={t('backoffice.reservations.etatsDesLieux.signatureZone')}
        style={{ border: `1px dashed ${couleurs.bordure}`, borderRadius: 8, width: '100%', maxWidth: 440, touchAction: 'none', cursor: 'crosshair' }}
        onPointerDown={demarrer}
        onPointerMove={dessiner}
        onPointerUp={arreter}
        onPointerLeave={arreter}
      />
      <Typography.Text type="secondary" style={{ display: 'block', fontSize: 12, marginTop: 4 }}>
        {t('backoffice.reservations.etatsDesLieux.signatureAide')}
      </Typography.Text>
      <Space style={{ marginTop: 8 }}>
        <Button onClick={effacer} disabled={!aDessine}>
          {t('backoffice.reservations.etatsDesLieux.effacer')}
        </Button>
        <Button
          type="primary"
          disabled={!aDessine}
          loading={enCours}
          onClick={() => {
            const toile = toileRef.current
            if (!toile) return
            onValider(toile.toDataURL('image/png'))
          }}
        >
          {t('backoffice.reservations.etatsDesLieux.signer')}
        </Button>
      </Space>
    </div>
  )
}
