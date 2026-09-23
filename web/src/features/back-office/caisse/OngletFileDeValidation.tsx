import { RightOutlined } from '@ant-design/icons'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, Popconfirm, Space, Table, Typography, Upload } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { Modal } from '../../../shared/composants/PopupModal'
import { formaterPrix } from '../../../shared/format/devise'
import { couleurs } from '../../../shared/theme/jetons'
import { finaliserUnReglement, joindreLaPreuve, listerLesReglements, rejeterUnReglement, validerUnReglement } from './api'
import type { Reglement } from './types'

/** Le circuit à 4 étapes (CdC § 8.1), affiché comme repère fixe — pas un traqueur de progression d'UN règlement, la file en contient plusieurs à des stades différents simultanément (brief refonte §18). */
function CircuitDeValidation() {
  const { t } = useTranslation()
  const etapes = [
    t('backoffice.caisse.file.etapes.saisie'),
    t('backoffice.caisse.file.etapes.validation'),
    t('backoffice.caisse.file.etapes.preuve'),
    t('backoffice.caisse.file.etapes.finalisation'),
  ]
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 10, flexWrap: 'wrap', marginBottom: 24, padding: '12px 16px', background: couleurs.blanc, border: `1px solid ${couleurs.bordure}`, borderRadius: 10 }}>
      {etapes.map((etape, i) => (
        <span key={etape} style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <Typography.Text style={{ fontSize: 13, color: couleurs.texteDiscret }}>{etape}</Typography.Text>
          {i < etapes.length - 1 && <RightOutlined style={{ fontSize: 10, color: couleurs.sable }} />}
        </span>
      ))}
    </div>
  )
}

/** Back office › Caisse › File de validation (circuit à 3 personnes : saisie → validation → preuve → finalisation, CdC § 8.1). */
export function OngletFileDeValidation() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [erreur, setErreur] = useState<string | null>(null)
  const [reglementPourPreuve, setReglementPourPreuve] = useState<Reglement | null>(null)
  const [reglementPourRejet, setReglementPourRejet] = useState<Reglement | null>(null)
  const [motifRejet, setMotifRejet] = useState('')

  const reglements = useQuery({
    queryKey: ['backoffice', 'caisse', 'reglements', 'file-de-validation'],
    queryFn: () => listerLesReglements({ par_page: 100 }),
  })

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'caisse', 'reglements'] })

  const surErreur = (e: unknown) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique'))

  const valider = useMutation({ mutationFn: (id: number) => validerUnReglement(id), onSuccess: invalider, onError: surErreur })
  const finaliser = useMutation({ mutationFn: (id: number) => finaliserUnReglement(id), onSuccess: invalider, onError: surErreur })
  const joindre = useMutation({
    mutationFn: ({ id, fichier }: { id: number; fichier: File }) => joindreLaPreuve(id, fichier),
    onSuccess: () => {
      setReglementPourPreuve(null)
      void invalider()
    },
    onError: surErreur,
  })
  const rejeter = useMutation({
    mutationFn: ({ id, motif }: { id: number; motif: string }) => rejeterUnReglement(id, motif),
    onSuccess: () => {
      setReglementPourRejet(null)
      setMotifRejet('')
      void invalider()
    },
    onError: surErreur,
  })

  const tous = reglements.data?.elements ?? []
  const groupes: { etat: string; titre: string; lignes: Reglement[] }[] = [
    { etat: 'en_attente', titre: t('backoffice.caisse.file.enAttenteDeValidation'), lignes: tous.filter((r) => r.etat === 'en_attente') },
    { etat: 'a_payer', titre: t('backoffice.caisse.file.aPayer'), lignes: tous.filter((r) => r.etat === 'a_payer') },
    { etat: 'preuve_jointe', titre: t('backoffice.caisse.file.aFinaliser'), lignes: tous.filter((r) => r.etat === 'preuve_jointe') },
  ]

  return (
    <div>
      <CircuitDeValidation />

      {erreur && <Alert style={{ marginBottom: 16 }} type="error" showIcon title={erreur} closable onClose={() => setErreur(null)} />}

      {groupes.map((groupe) => (
        <div key={groupe.etat} style={{ marginBottom: 24 }}>
          <Space align="center" size={10} style={{ marginBottom: 8 }}>
            <Typography.Title level={5} style={{ margin: 0 }}>
              {groupe.titre}
            </Typography.Title>
            {groupe.lignes.length > 0 && (
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  minWidth: 20,
                  height: 20,
                  padding: '0 6px',
                  borderRadius: 999,
                  background: couleurs.sableClair,
                  color: couleurs.bleuNuit,
                  fontSize: 12,
                  fontWeight: 700,
                }}
              >
                {groupe.lignes.length}
              </span>
            )}
          </Space>
          <Table<Reglement>
            rowKey="id"
            size="small"
            loading={reglements.isPending}
            dataSource={groupe.lignes}
            pagination={false}
            locale={{ emptyText: t('backoffice.caisse.file.aucunReglement') }}
            columns={[
              { title: t('backoffice.caisse.registre.reference'), dataIndex: 'reference' },
              { title: t('backoffice.caisse.registre.sens'), dataIndex: 'sens', render: (v: string) => t(`backoffice.caisse.sens.${v}`) },
              { title: t('backoffice.caisse.registre.guichet'), dataIndex: 'guichet_libelle' },
              { title: t('backoffice.caisse.registre.tiers'), dataIndex: ['tiers', 'nom'] },
              { title: t('backoffice.caisse.registre.montant'), dataIndex: 'montant', render: (v: number) => formaterPrix(v) },
              { title: t('backoffice.caisse.registre.mode'), dataIndex: 'mode_libelle' },
              { title: t('backoffice.caisse.file.saisiPar'), key: 'saisi', render: (_, r) => `${r.circuit.saisie.par} — ${r.circuit.saisie.le}` },
              {
                title: '',
                key: 'actions',
                render: (_, r) => (
                  <Space>
                    {r.actions.valider && (
                      <Button size="small" type="primary" loading={valider.isPending} onClick={() => valider.mutate(r.id)}>
                        {t('backoffice.caisse.file.valider')}
                      </Button>
                    )}
                    {r.actions.joindre_la_preuve && (
                      <Button size="small" onClick={() => setReglementPourPreuve(r)}>
                        {t('backoffice.caisse.file.joindreLaPreuve')}
                      </Button>
                    )}
                    {r.actions.finaliser && (
                      <Button size="small" type="primary" loading={finaliser.isPending} onClick={() => finaliser.mutate(r.id)}>
                        {t('backoffice.caisse.file.finaliser')}
                      </Button>
                    )}
                    {r.actions.rejeter && (
                      <Button size="small" danger onClick={() => setReglementPourRejet(r)}>
                        {t('backoffice.caisse.file.rejeter')}
                      </Button>
                    )}
                  </Space>
                ),
              },
            ]}
          />
        </div>
      ))}

      <Modal
        title={t('backoffice.caisse.file.joindreLaPreuve')}
        open={reglementPourPreuve !== null}
        onCancel={() => setReglementPourPreuve(null)}
        footer={null}
        destroyOnHidden
      >
        <Typography.Paragraph type="secondary">{t('backoffice.caisse.file.preuveAide')}</Typography.Paragraph>
        <Upload
          showUploadList={false}
          accept="application/pdf,image/jpeg,image/png"
          beforeUpload={(fichier) => {
            if (reglementPourPreuve) joindre.mutate({ id: reglementPourPreuve.id, fichier })
            return false
          }}
        >
          <Button loading={joindre.isPending}>{t('backoffice.caisse.file.choisirUnFichier')}</Button>
        </Upload>
      </Modal>

      <Modal
        title={t('backoffice.caisse.file.rejeter')}
        open={reglementPourRejet !== null}
        onCancel={() => {
          setReglementPourRejet(null)
          setMotifRejet('')
        }}
        footer={null}
        destroyOnHidden
      >
        <Typography.Text>{t('backoffice.caisse.file.motifDuRejet')}</Typography.Text>
        <Input.TextArea value={motifRejet} onChange={(e) => setMotifRejet(e.target.value)} rows={3} style={{ marginTop: 8, marginBottom: 12 }} />
        <Popconfirm
          title={t('backoffice.caisse.file.confirmerLeRejet')}
          onConfirm={() => {
            if (reglementPourRejet) rejeter.mutate({ id: reglementPourRejet.id, motif: motifRejet })
          }}
        >
          <Button danger type="primary" loading={rejeter.isPending} disabled={motifRejet.trim().length < 5}>
            {t('backoffice.caisse.file.rejeter')}
          </Button>
        </Popconfirm>
      </Modal>
    </div>
  )
}
