import { useQueryClient, useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Skeleton, Space, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi } from '../../shared/api/client'
import { useConfirmerAction } from '../../shared/composants/confirmer'
import { formaterDate } from '../../shared/format/date'
import { PageConfirmation, SectionTransformationDevis } from '../reservation/PageTunnelReservation'
import { ResumeDevis } from '../reservation/ResumeDevis'
import type { Sejour } from '../reservation/types'
import { archiverMonDevis, monDevis } from './api'

/** Espace client › Détail d'un devis (CdC § 5.1, 5.3) : transformation d'un clic, ou archivage. */
export function PageDetailDevis() {
  const { t } = useTranslation()
  const { reference } = useParams<{ reference: string }>()
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [erreur, setErreur] = useState<string | null>(null)
  const [transforme, setTransforme] = useState<Sejour | null>(null)

  const devis = useQuery({
    queryKey: ['client', 'devis', reference],
    queryFn: () => monDevis(reference ?? ''),
    enabled: !!reference,
  })

  const archivage = useMutation({
    mutationFn: () => archiverMonDevis(reference ?? ''),
    onSuccess: async (donnees) => {
      queryClient.setQueryData(['client', 'devis', reference], donnees)
      await queryClient.invalidateQueries({ queryKey: ['client', 'devis'] })
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  if (devis.isPending) return <Skeleton active paragraph={{ rows: 8 }} />
  if (!devis.data) return <Alert type="error" showIcon title={t('client.sejours.introuvable')} />

  const d = devis.data

  if (transforme) {
    return <PageConfirmation resultat={{ type: 'sejour', sejour: transforme }} />
  }

  if (d.sejour) {
    return (
      <div>
        <Link to="..">{t('client.detail.retourALaListe')}</Link>
        <Alert
          style={{ marginTop: 16 }}
          type="success"
          showIcon
          title={t('client.detail.devisDejaTransforme', { reference: d.sejour })}
        />
      </div>
    )
  }

  return (
    <div>
      <Link to="..">{t('client.detail.retourALaListe')}</Link>
      <Typography.Title level={3} style={{ marginTop: 8 }}>
        {d.reference} <Tag>{d.etat_libelle}</Tag>
      </Typography.Title>
      <Typography.Paragraph>
        <strong>{d.logement.nom}</strong> — {d.logement.lieu.quartier}, {d.logement.lieu.commune}
      </Typography.Paragraph>
      <Typography.Paragraph>
        {t('tunnel.arrivee')} : {formaterDate(d.arrivee)} · {t('tunnel.depart')} : {formaterDate(d.depart)}
      </Typography.Paragraph>

      <ResumeDevis devis={d.devis} />

      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}

      {d.etat === 'en_attente' && (
        <>
          <SectionTransformationDevis
            reference={d.reference}
            onTransforme={(sejour) => {
              setTransforme(sejour)
              void queryClient.invalidateQueries({ queryKey: ['client', 'devis'] })
            }}
          />
          <Space style={{ marginTop: 16 }}>
            <Button
              danger
              loading={archivage.isPending}
              onClick={async () => {
                const confirme = await confirmer({ titre: t('client.detail.confirmerArchivage'), danger: true })
                if (confirme) archivage.mutate()
              }}
            >
              {t('client.detail.archiverLeDevis')}
            </Button>
          </Space>
        </>
      )}
    </div>
  )
}
