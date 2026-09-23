import { useQueryClient, useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Card, Image, Input, InputNumber, Skeleton, Space, Tag, Typography, Upload } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi } from '../../../shared/api/client'
import { useConfirmerAction } from '../../../shared/composants/confirmer'
import { StatutBadge } from '../../../shared/composants/StatutBadge'
import { formaterPrix } from '../../../shared/format/devise'
import {
  afficherLaPublication,
  afficherLaSituationDuPrix,
  afficherLeLogement,
  agirSurLaPublication,
  ajouterUnePhoto,
  definirLaCouverture,
  listerLesPhotos,
  proposerLaDerogationDePourcentage,
  proposerLePrixDeVente,
  proposerLePrixProprietaire,
  reordonnerLesPhotos,
  supprimerUnePhoto,
} from './api'
import type { ActionDePublication } from './types'

const ACTIONS_PAR_ETAT: Record<string, ActionDePublication[]> = {
  brouillon: ['soumettre'],
  en_attente: ['publier', 'refuser'],
  refuse: ['soumettre'],
  prix_a_negocier: [],
  publie: ['suspendre'],
  suspendu: ['reactiver'],
}

/** Back office › Catalogue › Détail d'un logement (CdC § 7.1) : prix, publication, photos. */
export function PageDetailLogement() {
  const { t } = useTranslation()
  const { id, logementId } = useParams<{ id: string; logementId: string }>()
  const residenceId = Number(id)
  const idNombre = Number(logementId)
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [erreur, setErreur] = useState<string | null>(null)

  const cle = ['backoffice', 'residences', residenceId, 'logements', idNombre] as const
  const logement = useQuery({ queryKey: cle, queryFn: () => afficherLeLogement(residenceId, idNombre), enabled: Number.isFinite(idNombre) })
  const prix = useQuery({ queryKey: [...cle, 'prix'], queryFn: () => afficherLaSituationDuPrix(residenceId, idNombre), enabled: Number.isFinite(idNombre) })
  const publication = useQuery({ queryKey: [...cle, 'publication'], queryFn: () => afficherLaPublication(residenceId, idNombre), enabled: Number.isFinite(idNombre) })
  const photos = useQuery({ queryKey: [...cle, 'photos'], queryFn: () => listerLesPhotos(residenceId, idNombre), enabled: Number.isFinite(idNombre) })

  const [montantProprietaire, setMontantProprietaire] = useState<number>(0)
  const [montantVente, setMontantVente] = useState<number>(0)
  const [motifVente, setMotifVente] = useState('')

  const surErreur = (e: unknown) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))
  // Le résumé en tête de section lit le LOGEMENT (prix_proprietaire, prix_vente, marge_par_nuitee),
  // pas seulement la situation détaillée : les deux doivent être invalidés ensemble, sinon le
  // résumé reste périmé après un changement de prix (défaut trouvé en vérification réelle).
  const invaliderPrix = () => {
    void queryClient.invalidateQueries({ queryKey: [...cle, 'prix'] })
    void queryClient.invalidateQueries({ queryKey: cle })
  }

  const proposerProprietaire = useMutation({
    mutationFn: (nature: 'proposition' | 'accord') => proposerLePrixProprietaire(residenceId, idNombre, montantProprietaire, nature),
    onSuccess: invaliderPrix,
    onError: surErreur,
  })
  const proposerVente = useMutation({
    mutationFn: () => proposerLePrixDeVente(residenceId, idNombre, montantVente, motifVente || undefined),
    onSuccess: invaliderPrix,
    onError: surErreur,
  })
  const [tauxDerogation, setTauxDerogation] = useState<number | null>(null)
  const proposerDerogation = useMutation({
    mutationFn: () => proposerLaDerogationDePourcentage(residenceId, idNombre, tauxDerogation),
    onSuccess: invaliderPrix,
    onError: surErreur,
  })
  const agirPublication = useMutation({
    mutationFn: (action: ActionDePublication) => agirSurLaPublication(residenceId, idNombre, action, action === 'refuser' || action === 'suspendre' ? 'Décision de la direction.' : undefined),
    onSuccess: (donnees) => {
      queryClient.setQueryData(cle, donnees)
      void queryClient.invalidateQueries({ queryKey: [...cle, 'publication'] })
    },
    onError: surErreur,
  })
  const invaliderPhotos = () => queryClient.invalidateQueries({ queryKey: [...cle, 'photos'] })
  const televerser = useMutation({ mutationFn: (fichier: File) => ajouterUnePhoto(residenceId, idNombre, fichier), onSuccess: invaliderPhotos, onError: surErreur })
  const couvrir = useMutation({ mutationFn: (photoId: number) => definirLaCouverture(residenceId, idNombre, photoId), onSuccess: invaliderPhotos, onError: surErreur })
  const reordonner = useMutation({ mutationFn: (ordre: number[]) => reordonnerLesPhotos(residenceId, idNombre, ordre), onSuccess: invaliderPhotos, onError: surErreur })
  const supprimer = useMutation({
    mutationFn: (photoId: number) => supprimerUnePhoto(residenceId, idNombre, photoId, 'Retrait décidé par la direction.'),
    onSuccess: invaliderPhotos,
    onError: surErreur,
  })

  if (logement.isPending) return <Skeleton active paragraph={{ rows: 10 }} />
  if (!logement.data) return <Alert type="error" showIcon title={t('client.sejours.introuvable')} />

  const l = logement.data
  const actionsPossibles = ACTIONS_PAR_ETAT[l.etat_publication] ?? []
  const photosTriees = [...(photos.data?.photos ?? [])].sort((a, b) => a.ordre - b.ordre)

  const deplacer = (index: number, sens: -1 | 1): void => {
    const cible = index + sens
    if (cible < 0 || cible >= photosTriees.length) return
    const copie = [...photosTriees]
    ;[copie[index], copie[cible]] = [copie[cible], copie[index]]
    reordonner.mutate(copie.map((p) => p.id))
  }

  return (
    <div>
      <Link to="..">{t('client.detail.retourALaListe')}</Link>
      <Typography.Title level={3} style={{ marginTop: 8 }}>
        {l.reference} — {l.nom} <Tag color="default">{l.type.nom}</Tag>
      </Typography.Title>
      <Typography.Paragraph type="secondary">{l.resume}</Typography.Paragraph>

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      <Card title={t('backoffice.catalogue.prix.titre')} style={{ marginBottom: 16 }}>
        <Space orientation="vertical" style={{ width: '100%' }}>
          <Typography.Paragraph style={{ margin: 0 }}>
            {t('backoffice.catalogue.prix.proprietaire')} : {l.prix_proprietaire ? formaterPrix(l.prix_proprietaire) : t('backoffice.catalogue.prix.nonFixe')} —{' '}
            {t('backoffice.catalogue.prix.vente')} : {l.prix_vente ? formaterPrix(l.prix_vente) : t('backoffice.catalogue.prix.nonFixe')} —{' '}
            {t('backoffice.catalogue.prix.marge')} : {l.marge_par_nuitee !== null ? formaterPrix(l.marge_par_nuitee) : '—'}
          </Typography.Paragraph>
          {prix.data?.prix_de_vente_conseille && (
            <Typography.Paragraph type="secondary" style={{ margin: 0 }}>
              {t('backoffice.catalogue.prix.conseille')} : {formaterPrix(prix.data.prix_de_vente_conseille)}
            </Typography.Paragraph>
          )}
          {prix.data?.changement_en_attente && (
            <Alert
              type="warning"
              showIcon
              title={t('backoffice.catalogue.prix.propositionEnAttente', {
                montant: formaterPrix(Number(prix.data.changement_en_attente.valeur_proposee)),
              })}
            />
          )}

          <Space wrap align="start" style={{ marginTop: 8 }}>
            <Space orientation="vertical" size={4}>
              <Typography.Text strong>{t('backoffice.catalogue.prix.proprietaire')}</Typography.Text>
              <InputNumber min={1} step={1000} onChange={(v) => setMontantProprietaire(v ?? 0)} placeholder={t('backoffice.catalogue.prix.montant')} />
              <Space>
                <Button size="small" loading={proposerProprietaire.isPending} onClick={() => proposerProprietaire.mutate('proposition')}>
                  {t('backoffice.catalogue.prix.proposer')}
                </Button>
                <Button size="small" type="primary" loading={proposerProprietaire.isPending} onClick={() => proposerProprietaire.mutate('accord')}>
                  {t('backoffice.catalogue.prix.arreter')}
                </Button>
              </Space>
            </Space>

            <Space orientation="vertical" size={4}>
              <Typography.Text strong>{t('backoffice.catalogue.prix.vente')}</Typography.Text>
              <InputNumber min={1} step={1000} onChange={(v) => setMontantVente(v ?? 0)} placeholder={t('backoffice.catalogue.prix.montant')} />
              <Input placeholder={t('backoffice.catalogue.prix.motif')} value={motifVente} onChange={(e) => setMotifVente(e.target.value)} style={{ width: 200 }} />
              <Button size="small" type="primary" loading={proposerVente.isPending} onClick={() => proposerVente.mutate()}>
                {t('backoffice.catalogue.prix.proposer')}
              </Button>
            </Space>

            <Space orientation="vertical" size={4}>
              <Typography.Text strong>{t('backoffice.catalogue.prix.derogation')}</Typography.Text>
              <Typography.Text type="secondary">
                {t('backoffice.catalogue.prix.pourcentageActuel', { taux: prix.data?.pourcentage_entreprise ?? '—' })}
                {prix.data?.pourcentage_entreprise_derogation !== null && prix.data?.pourcentage_entreprise_derogation !== undefined
                  ? ` (${t('backoffice.catalogue.prix.derogationActive')})`
                  : ''}
              </Typography.Text>
              <InputNumber min={0} max={500} onChange={(v) => setTauxDerogation(v)} placeholder={t('backoffice.catalogue.prix.nouveauTaux')} />
              <Button size="small" type="primary" loading={proposerDerogation.isPending} onClick={() => proposerDerogation.mutate()}>
                {t('backoffice.catalogue.prix.proposer')}
              </Button>
            </Space>
          </Space>

          <Link to={`/backoffice/tarification?logement_id=${idNombre}`}>{t('backoffice.catalogue.prix.voirGrilleTarifaire')}</Link>
        </Space>
      </Card>

      <Card title={t('backoffice.catalogue.publication.titre')} style={{ marginBottom: 16 }}>
        <StatutBadge domaine="publicationLogement" code={l.etat_publication} libelle={l.etat_publication_libelle} />
        {publication.data && publication.data.obstacles.length > 0 && (
          <Alert
            style={{ marginTop: 12 }}
            type="warning"
            showIcon
            title={t('backoffice.catalogue.publication.obstacles')}
            description={
              <ul style={{ margin: 0, paddingInlineStart: 20 }}>
                {publication.data.obstacles.map((o) => (
                  <li key={o}>{o}</li>
                ))}
              </ul>
            }
          />
        )}
        {publication.data?.motif && <Typography.Paragraph type="danger" style={{ marginTop: 12 }}>{publication.data.motif}</Typography.Paragraph>}
        <Space style={{ marginTop: 12 }}>
          {actionsPossibles.map((action) => (
            <Button
              key={action}
              type={action === 'publier' || action === 'soumettre' || action === 'reactiver' ? 'primary' : 'default'}
              danger={action === 'refuser' || action === 'suspendre'}
              loading={agirPublication.isPending}
              disabled={(action === 'publier' || action === 'reactiver') && (publication.data?.obstacles.length ?? 0) > 0}
              onClick={async () => {
                const confirme = await confirmer({ titre: t(`backoffice.catalogue.publication.${action}`), danger: action === 'refuser' || action === 'suspendre' })
                if (confirme) agirPublication.mutate(action)
              }}
            >
              {t(`backoffice.catalogue.publication.${action}`)}
            </Button>
          ))}
        </Space>
      </Card>

      <Card title={t('backoffice.catalogue.photos.titre')}>
        {photos.data && (
          <Typography.Paragraph type="secondary">
            {t('backoffice.catalogue.photos.compte', { nombre: photos.data.nombre, minimum: photos.data.minimum })}
          </Typography.Paragraph>
        )}
        <Space wrap align="start">
          {photosTriees.map((photo, index) => (
            <Card
              key={photo.id}
              size="small"
              style={{ width: 180 }}
              cover={<Image src={photo.url_vignette} alt={photo.legende ?? ''} height={120} style={{ objectFit: 'cover' }} />}
            >
              {photo.couverture && <Tag color="blue">{t('backoffice.catalogue.photos.couverture')}</Tag>}
              <div style={{ marginTop: 8, display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                {!photo.couverture && (
                  <Button size="small" onClick={() => couvrir.mutate(photo.id)}>
                    {t('backoffice.catalogue.photos.definirCouverture')}
                  </Button>
                )}
                <Button size="small" onClick={() => deplacer(index, -1)} disabled={index === 0}>
                  ↑
                </Button>
                <Button size="small" onClick={() => deplacer(index, 1)} disabled={index === photosTriees.length - 1}>
                  ↓
                </Button>
                <Button
                  size="small"
                  danger
                  onClick={async () => {
                    const confirme = await confirmer({ titre: t('backoffice.catalogue.photos.confirmerSuppression'), danger: true })
                    if (confirme) supprimer.mutate(photo.id)
                  }}
                >
                  {t('backoffice.catalogue.photos.supprimer')}
                </Button>
              </div>
            </Card>
          ))}
        </Space>

        <Upload
          style={{ marginTop: 16 }}
          showUploadList={false}
          accept="image/jpeg,image/png"
          beforeUpload={(fichier) => {
            televerser.mutate(fichier)
            return false
          }}
        >
          <Button style={{ marginTop: 16 }} loading={televerser.isPending}>
            {t('backoffice.catalogue.photos.ajouter')}
          </Button>
        </Upload>
      </Card>
    </div>
  )
}
