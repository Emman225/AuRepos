import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Input, InputNumber, Popconfirm, Select, Space, Switch, Table, Tag, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../../shared/api/client'
import { BoutonsExport } from '../../../shared/composants/BoutonsExport'
import { Modal } from '../../../shared/composants/PopupModal'
import { creerUneBanniere, listerLesBannieres, modifierUneBanniere, supprimerUneBanniere } from './api'
import type { Banniere, SaisieBanniere } from './types'

/** Paramètres › Divers › Bannières (CdC § 5.1, P1-BO-10) : promotions de la page d'accueil. */
export function OngletBannieres() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [actif, setActif] = useState<boolean | undefined>(undefined)
  const [nouvelleOuverte, setNouvelleOuverte] = useState(false)
  const [banniereOuverte, setBanniereOuverte] = useState<Banniere | null>(null)

  const bannieres = useQuery({ queryKey: ['backoffice', 'bannieres'], queryFn: listerLesBannieres })
  const elements = (bannieres.data ?? []).filter((b) => actif === undefined || b.actif === actif)

  const invalider = () => queryClient.invalidateQueries({ queryKey: ['backoffice', 'bannieres'] })

  const supprimer = useMutation({
    mutationFn: (id: number) => supprimerUneBanniere(id),
    onSuccess: () => void invalider(),
  })

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
        <Select
          style={{ width: 180 }}
          allowClear
          placeholder={t('backoffice.parametres.bannieres.actif')}
          value={actif}
          onChange={setActif}
          options={[
            { value: true, label: t('backoffice.agences.active') },
            { value: false, label: t('backoffice.agences.inactive') },
          ]}
        />
        <Space>
          <BoutonsExport url="/backoffice/bannieres/export" filtres={{ actif }} nomFichier="Bannieres" />
          <Button type="primary" onClick={() => setNouvelleOuverte(true)}>
            {t('backoffice.parametres.bannieres.nouvelle')}
          </Button>
        </Space>
      </div>

      <Table<Banniere>
        rowKey="id"
        size="small"
        loading={bannieres.isPending}
        dataSource={elements}
        pagination={{ pageSize: 5, showSizeChanger: true, pageSizeOptions: [5, 10, 20, 50, 100] }}
        onRow={(b) => ({ onClick: () => setBanniereOuverte(b), style: { cursor: 'pointer' } })}
        columns={[
          { title: t('backoffice.parametres.bannieres.titre'), dataIndex: 'titre' },
          { title: t('backoffice.parametres.bannieres.ordre'), dataIndex: 'ordre' },
          {
            title: t('backoffice.parametres.bannieres.actif'),
            dataIndex: 'actif',
            render: (v: boolean) => <Tag color={v ? 'green' : 'default'}>{v ? t('backoffice.agences.active') : t('backoffice.agences.inactive')}</Tag>,
          },
          {
            title: '',
            key: 'actions',
            render: (_, b) => (
              <Popconfirm
                title={t('backoffice.parametres.bannieres.confirmerSuppression')}
                onConfirm={(e) => { e?.stopPropagation(); supprimer.mutate(b.id) }}
                onCancel={(e) => e?.stopPropagation()}
              >
                <Button danger size="small" onClick={(e) => e.stopPropagation()}>
                  {t('backoffice.parametres.bannieres.supprimer')}
                </Button>
              </Popconfirm>
            ),
          },
        ]}
      />

      <Modal title={t('backoffice.parametres.bannieres.nouvelle')} open={nouvelleOuverte} onCancel={() => setNouvelleOuverte(false)} footer={null} destroyOnHidden>
        <FormulaireBanniere banniere={null} onEnregistree={() => { setNouvelleOuverte(false); void invalider() }} />
      </Modal>

      <Modal title={banniereOuverte?.titre} open={banniereOuverte !== null} onCancel={() => setBanniereOuverte(null)} footer={null} destroyOnHidden>
        {banniereOuverte && (
          <FormulaireBanniere banniere={banniereOuverte} onEnregistree={() => { setBanniereOuverte(null); void invalider() }} />
        )}
      </Modal>
    </div>
  )
}

function FormulaireBanniere({ banniere, onEnregistree }: { banniere: Banniere | null; onEnregistree: () => void }) {
  const { t } = useTranslation()
  const [titre, setTitre] = useState(banniere?.titre ?? '')
  const [sousTitre, setSousTitre] = useState(banniere?.sous_titre ?? '')
  const [imageUrl, setImageUrl] = useState(banniere?.image_url ?? '')
  const [lien, setLien] = useState(banniere?.lien ?? '')
  const [ordre, setOrdre] = useState(banniere?.ordre ?? 0)
  const [actif, setActif] = useState(banniere?.actif ?? true)
  const [erreur, setErreur] = useState<string | null>(null)

  const enregistrer = useMutation({
    mutationFn: () => {
      const saisie: SaisieBanniere = {
        titre, sous_titre: sousTitre || undefined, image_url: imageUrl, lien: lien || undefined, ordre, actif,
      }
      return banniere ? modifierUneBanniere(banniere.id, saisie) : creerUneBanniere(saisie)
    },
    onSuccess: onEnregistree,
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  return (
    <Space orientation="vertical" style={{ width: '100%' }}>
      <Typography.Text>{t('backoffice.parametres.bannieres.titre')}</Typography.Text>
      <Input value={titre} onChange={(e) => setTitre(e.target.value)} />
      <Typography.Text>{t('backoffice.parametres.bannieres.sousTitre')}</Typography.Text>
      <Input value={sousTitre} onChange={(e) => setSousTitre(e.target.value)} />
      <Typography.Text>{t('backoffice.parametres.bannieres.image')}</Typography.Text>
      <Input value={imageUrl} onChange={(e) => setImageUrl(e.target.value)} placeholder="https://…" />
      <Typography.Text>{t('backoffice.parametres.bannieres.lien')}</Typography.Text>
      <Input value={lien} onChange={(e) => setLien(e.target.value)} placeholder="https://…" />
      <Typography.Text>{t('backoffice.parametres.bannieres.ordre')}</Typography.Text>
      <InputNumber style={{ width: '100%' }} value={ordre} onChange={(v) => setOrdre(v ?? 0)} min={0} />
      <Space>
        <Switch checked={actif} onChange={setActif} />
        <Typography.Text>{t('backoffice.parametres.bannieres.actif')}</Typography.Text>
      </Space>
      {erreur && <Alert type="error" showIcon title={erreur} />}
      <Button type="primary" loading={enregistrer.isPending} disabled={!titre || !imageUrl} onClick={() => enregistrer.mutate()}>
        {t('backoffice.reservations.manuelle.enregistrer')}
      </Button>
    </Space>
  )
}
