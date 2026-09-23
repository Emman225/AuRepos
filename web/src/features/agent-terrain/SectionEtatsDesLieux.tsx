import { PaperClipOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Button, Card, Input, Space, Tag, Typography, Upload } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { SignaturePad } from '../../shared/composants/SignaturePad'
import { couleurs } from '../../shared/theme/jetons'
import {
  ajouterUneLigneEtatDesLieux,
  ajouterUnePhotoDeLigne,
  etablirUnEtatDesLieux,
  listerLesEtatsDesLieux,
  signerUnEtatDesLieux,
  urlPdfEtatDesLieux,
} from './api'
import type { EtatDesLieux, TypeEtatDesLieux } from './types'

interface CarteProps {
  sejourId: number
  etatDesLieu: EtatDesLieux
  onChange: () => void
}

function CarteEtatDesLieux({ sejourId, etatDesLieu, onChange }: CarteProps) {
  const { t } = useTranslation()
  const [libelle, setLibelle] = useState('')
  const [observation, setObservation] = useState('')

  const ajouterLigne = useMutation({
    mutationFn: () => ajouterUneLigneEtatDesLieux(sejourId, etatDesLieu.id, libelle, observation || undefined),
    onSuccess: () => {
      setLibelle('')
      setObservation('')
      onChange()
    },
  })

  const signer = useMutation({
    mutationFn: (signature: string) => signerUnEtatDesLieux(sejourId, etatDesLieu.id, signature),
    onSuccess: onChange,
  })

  return (
    <Card
      size="small"
      style={{ marginBottom: 16 }}
      title={
        <Space>
          <span>{etatDesLieu.type_libelle}</span>
          <StatutBadge
            domaine="piece"
            code={etatDesLieu.signe ? 'validee' : 'en_attente'}
            libelle={etatDesLieu.signe ? t('backoffice.reservations.etatsDesLieux.signe') : t('backoffice.reservations.etatsDesLieux.nonSigne')}
          />
        </Space>
      }
      extra={
        <a href={urlPdfEtatDesLieux(sejourId)} target="_blank" rel="noreferrer">
          {t('backoffice.reservations.etatsDesLieux.voirPdf')}
        </a>
      }
    >
      {etatDesLieu.commentaire_general && (
        <Typography.Paragraph type="secondary" style={{ marginBottom: 12 }}>
          {etatDesLieu.commentaire_general}
        </Typography.Paragraph>
      )}

      <Space direction="vertical" style={{ width: '100%' }} size={10}>
        {etatDesLieu.lignes.map((ligne) => (
          <div key={ligne.id} style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: '8px 12px' }}>
            <Typography.Text strong>{ligne.libelle}</Typography.Text>
            {ligne.observation && (
              <Typography.Paragraph type="secondary" style={{ margin: '4px 0' }}>
                {ligne.observation}
              </Typography.Paragraph>
            )}
            <Space wrap size={6} style={{ marginTop: 6 }}>
              {ligne.photos.map((p) => (
                <Tag key={p.id} icon={<PaperClipOutlined />}>
                  {p.nom_original}
                </Tag>
              ))}
              {!etatDesLieu.signe && (
                <Upload
                  showUploadList={false}
                  accept="image/jpeg,image/png"
                  beforeUpload={(fichier) => {
                    void ajouterUnePhotoDeLigne(sejourId, etatDesLieu.id, ligne.id, fichier).then(() => onChange())
                    return false
                  }}
                >
                  <Button size="small">{t('backoffice.reservations.etatsDesLieux.ajouterPhoto')}</Button>
                </Upload>
              )}
            </Space>
          </div>
        ))}
      </Space>

      {!etatDesLieu.signe && (
        <>
          <Space wrap style={{ marginTop: 12, width: '100%' }}>
            <Input
              placeholder={t('backoffice.reservations.etatsDesLieux.libelle')}
              aria-label={t('backoffice.reservations.etatsDesLieux.libelle')}
              value={libelle}
              onChange={(e) => setLibelle(e.target.value)}
              style={{ width: 200 }}
            />
            <Input
              placeholder={t('backoffice.reservations.etatsDesLieux.observation')}
              aria-label={t('backoffice.reservations.etatsDesLieux.observation')}
              value={observation}
              onChange={(e) => setObservation(e.target.value)}
              style={{ width: 240 }}
            />
            <Button loading={ajouterLigne.isPending} disabled={libelle.trim().length === 0} onClick={() => ajouterLigne.mutate()}>
              {t('backoffice.reservations.etatsDesLieux.ajouterLigne')}
            </Button>
          </Space>

          <div style={{ marginTop: 16, borderTop: `1px solid ${couleurs.bordure}`, paddingTop: 16 }}>
            <Typography.Text strong style={{ display: 'block', marginBottom: 8 }}>
              {t('backoffice.reservations.etatsDesLieux.signature')}
            </Typography.Text>
            <SignaturePad enCours={signer.isPending} onValider={(signature) => signer.mutate(signature)} />
          </div>
        </>
      )}
    </Card>
  )
}

interface Props {
  sejourId: number
}

/** État des lieux d'entrée / de sortie (CdC § 6.3, P2-SEJ-02) : inventaire, photos, compteurs, signature écran, comparaison, PDF. */
export function SectionEtatsDesLieux({ sejourId }: Props) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const etats = useQuery({
    queryKey: ['agent', 'sejours', sejourId, 'etats-des-lieux'],
    queryFn: () => listerLesEtatsDesLieux(sejourId),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['agent', 'sejours', sejourId, 'etats-des-lieux'] })

  const etablir = useMutation({
    mutationFn: (type: TypeEtatDesLieux) => etablirUnEtatDesLieux(sejourId, type),
    onSuccess: invalider,
  })

  if (etats.isPending) return null

  const liste = Array.isArray(etats.data) ? etats.data : []
  const aEntree = liste.some((e) => e.type === 'entree')
  const aSortie = liste.some((e) => e.type === 'sortie')

  return (
    <Card title={t('backoffice.reservations.etatsDesLieux.titre')} style={{ marginBottom: 24 }}>
      <Space style={{ marginBottom: liste.length > 0 ? 16 : 0 }}>
        {!aEntree && (
          <Button loading={etablir.isPending} onClick={() => etablir.mutate('entree')}>
            {t('backoffice.reservations.etatsDesLieux.etablirEntree')}
          </Button>
        )}
        {!aSortie && (
          <Button loading={etablir.isPending} onClick={() => etablir.mutate('sortie')}>
            {t('backoffice.reservations.etatsDesLieux.etablirSortie')}
          </Button>
        )}
      </Space>

      {liste.map((e) => (
        <CarteEtatDesLieux key={e.id} sejourId={sejourId} etatDesLieu={e} onChange={invalider} />
      ))}
    </Card>
  )
}
