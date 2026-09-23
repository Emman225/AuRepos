import { useQueryClient, useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, InputNumber, Select, Skeleton, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi } from '../../shared/api/client'
import { EtatVide } from '../../shared/composants/EtatVide'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { useConfirmerAction } from '../../shared/composants/confirmer'
import { formaterDate } from '../../shared/format/date'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { ResumeDevis } from '../reservation/ResumeDevis'
import { annulerMonSejour, commanderUnRepas, mesCommandesRepas, mesTransfertsDuSejour, monSejour, restaurateursActifs } from './api'
import { FormulaireDemandeTransfert } from './FormulaireDemandeTransfert'
import type { CommandeRepas, ModeDeReglementRepas, RestaurateurActif, TransfertClient } from './types'

/** Espace client › Détail d'un séjour (CdC § 5.3) : code d'arrivée et adresse une fois confirmé. */
export function PageDetailSejour() {
  const { t } = useTranslation()
  const { reference } = useParams<{ reference: string }>()
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [erreur, setErreur] = useState<string | null>(null)

  const sejour = useQuery({
    queryKey: ['client', 'sejours', reference],
    queryFn: () => monSejour(reference ?? ''),
    enabled: !!reference,
  })

  const annulation = useMutation({
    mutationFn: () => annulerMonSejour(reference ?? ''),
    onSuccess: async (donnees) => {
      queryClient.setQueryData(['client', 'sejours', reference], donnees)
      await queryClient.invalidateQueries({ queryKey: ['client', 'sejours'] })
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  if (sejour.isPending) return <Skeleton active paragraph={{ rows: 8 }} />
  if (!sejour.data) return <Alert type="error" showIcon title={t('client.sejours.introuvable')} />

  const s = sejour.data

  return (
    <div>
      <Link to=".." style={{ fontSize: 13 }}>
        {t('client.detail.retourALaListe')}
      </Link>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 12, margin: '10px 0 20px' }}>
        <div>
          <Space align="center" size={12}>
            <Typography.Title level={3} style={{ margin: 0 }}>
              {s.reference}
            </Typography.Title>
            <StatutBadge domaine="sejour" code={s.etat} libelle={s.etat_libelle} />
          </Space>
          <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, marginTop: 4 }}>
            <Typography.Text strong>{s.logement.nom}</Typography.Text> — {s.logement.lieu.quartier}, {s.logement.lieu.commune}
          </Typography.Text>
        </div>
        <div style={{ textAlign: 'right' }}>
          <Typography.Text style={{ display: 'block', fontSize: 12, color: couleurs.texteDiscret }}>
            {t('client.sejours.netAPayer')}
          </Typography.Text>
          <Typography.Text strong style={{ fontSize: 22, color: couleurs.bleuNuit, fontVariantNumeric: 'tabular-nums' }}>
            {formaterPrix(s.net_a_payer)}
          </Typography.Text>
        </div>
      </div>

      <Typography.Paragraph style={{ color: couleurs.texteDiscret }}>
        {t('tunnel.arrivee')} : {formaterDate(s.arrivee)} · {t('tunnel.depart')} : {formaterDate(s.depart)} (
        {t('client.detail.nuits', { count: s.nombre_de_nuits })})
      </Typography.Paragraph>

      {s.code_d_arrivee && (
        <Alert
          style={{ marginBottom: 16 }}
          type="success"
          showIcon
          title={t('client.detail.codeArrivee', { code: s.code_d_arrivee })}
        />
      )}
      {s.acces?.adresse && (
        <Typography.Paragraph>
          <strong>{t('client.detail.adresse')}</strong> {s.acces.adresse}
          {s.acces.repere && ` — ${s.acces.repere}`}
        </Typography.Paragraph>
      )}
      {s.acces?.consignes && <Typography.Paragraph type="secondary">{s.acces.consignes}</Typography.Paragraph>}

      {s.devis && <ResumeDevis devis={s.devis} />}

      <Alert style={{ marginTop: 16 }} type="info" showIcon title={t('client.detail.factureBientot')} />

      <SectionCommandesRepas reference={s.reference} />

      <SectionTransferts reference={s.reference} />

      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}

      {s.etat === 'demande' && (
        <Space style={{ marginTop: 24 }}>
          <Button
            danger
            loading={annulation.isPending}
            onClick={async () => {
              const confirme = await confirmer({ titre: t('client.detail.confirmerAnnulation'), danger: true })
              if (confirme) annulation.mutate()
            }}
          >
            {t('client.detail.annulerLeSejour')}
          </Button>
        </Space>
      )}
    </div>
  )
}

/** Commandes de repas déjà passées sur ce séjour, et formulaire pour en passer une nouvelle (CdC — « Repas et boissons »). */
function SectionCommandesRepas({ reference }: { reference: string }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const commandes = useQuery({ queryKey: ['client', 'sejours', reference, 'commandes'], queryFn: () => mesCommandesRepas(reference) })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['client', 'sejours', reference, 'commandes'] })

  return (
    <div style={{ marginTop: 24 }}>
      <Typography.Title level={4}>{t('client.repas.titre')}</Typography.Title>

      {commandes.isPending ? (
        <Skeleton active paragraph={{ rows: 2 }} />
      ) : !commandes.data || commandes.data.length === 0 ? (
        <EtatVide titre={t('client.repas.aucune')} />
      ) : (
        <Space orientation="vertical" style={{ width: '100%', marginBottom: 16 }}>
          {commandes.data.map((c: CommandeRepas) => (
            <div key={c.id} style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <Space align="center">
                  <Typography.Text strong>{c.reference}</Typography.Text>
                  <StatutBadge domaine="commandeRepas" code={c.etat} libelle={c.etat_libelle} />
                </Space>
                <Typography.Text strong style={{ fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(c.montant_total)}
                </Typography.Text>
              </div>
              {c.etat === 'en_livraison' && c.code_livraison && (
                <Alert style={{ marginTop: 10 }} type="success" showIcon title={t('client.repas.codeLivraison', { code: c.code_livraison })} />
              )}
            </div>
          ))}
        </Space>
      )}

      <FormulaireCommandeRepas
        reference={reference}
        onCommandee={() => void invalider()}
      />
    </div>
  )
}

/** Transferts déjà demandés sur ce séjour, et formulaire pour en demander un nouveau (CdC § 5.2, § 6.6). */
function SectionTransferts({ reference }: { reference: string }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [formulaireOuvert, setFormulaireOuvert] = useState(false)
  const [message, setMessage] = useState<string | null>(null)

  const transferts = useQuery({ queryKey: ['client', 'sejours', reference, 'transferts'], queryFn: () => mesTransfertsDuSejour(reference) })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['client', 'sejours', reference, 'transferts'] })

  return (
    <div style={{ marginTop: 24 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          {t('client.transferts.titre')}
        </Typography.Title>
        <Button onClick={() => setFormulaireOuvert(true)}>{t('client.transferts.demander')}</Button>
      </div>

      {transferts.isPending ? (
        <Skeleton active paragraph={{ rows: 2 }} />
      ) : !transferts.data || transferts.data.length === 0 ? (
        <EtatVide titre={t('client.transferts.aucun')} />
      ) : (
        <Space orientation="vertical" style={{ width: '100%', marginTop: 16 }}>
          {transferts.data.map((tr: TransfertClient) => (
            <div key={tr.reference} style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <Space align="center">
                  <Typography.Text strong>{tr.reference}</Typography.Text>
                  <StatutBadge domaine="transfert" code={tr.etat} libelle={tr.etat_libelle} />
                </Space>
                <Typography.Text strong style={{ fontVariantNumeric: 'tabular-nums' }}>
                  {formaterPrix(tr.montant)}
                </Typography.Text>
              </div>
              <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, fontSize: 13, marginTop: 4 }}>
                {tr.lieu_de_prise_en_charge}
                {tr.commune && ` — ${tr.commune}`} · {tr.date_heure_prevue}
              </Typography.Text>
              {tr.etat === 'affecte' && tr.code_prise_en_charge && (
                <Alert
                  style={{ marginTop: 10 }}
                  type="success"
                  showIcon
                  title={t('client.transferts.codePriseEnCharge', { code: tr.code_prise_en_charge })}
                />
              )}
            </div>
          ))}
        </Space>
      )}

      {message && <Alert style={{ marginTop: 16 }} type="success" showIcon title={message} />}

      <FormulaireDemandeTransfert
        ouvert={formulaireOuvert}
        reference={reference}
        onFermer={() => setFormulaireOuvert(false)}
        onDemande={(demande) => {
          setFormulaireOuvert(false)
          setMessage(t('client.transferts.montantCalcule', { montant: formaterPrix(demande.montant) }))
          void invalider()
        }}
      />
    </div>
  )
}

function FormulaireCommandeRepas({ reference, onCommandee }: { reference: string; onCommandee: () => void }) {
  const { t } = useTranslation()
  const [restaurateurId, setRestaurateurId] = useState<number | undefined>(undefined)
  const [modeReglement, setModeReglement] = useState<ModeDeReglementRepas>('note_du_sejour')
  const [quantites, setQuantites] = useState<Record<number, number>>({})
  const [erreur, setErreur] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const restaurateurs = useQuery({ queryKey: ['client', 'restaurateurs'], queryFn: restaurateursActifs })
  const restaurateur = restaurateurs.data?.find((r: RestaurateurActif) => r.id === restaurateurId) ?? null

  const commander = useMutation({
    mutationFn: () => {
      const lignes = Object.entries(quantites)
        .filter(([, quantite]) => quantite > 0)
        .map(([produitId, quantite]) => ({ produit_id: Number(produitId), quantite }))
      return commanderUnRepas(reference, { restaurateur_id: restaurateurId!, mode_reglement: modeReglement, lignes })
    },
    onSuccess: (commande) => {
      setMessage(t('client.repas.commandeEnvoyee', { reference: commande.reference }))
      setErreur(null)
      setQuantites({})
      onCommandee()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const auMoinsUneLigne = Object.values(quantites).some((q) => q > 0)

  return (
    <div style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: 16, marginTop: 16 }}>
      <Typography.Title level={5} style={{ marginTop: 0 }}>
        {t('client.repas.commander')}
      </Typography.Title>
      <Space orientation="vertical" style={{ width: '100%' }}>
        <Typography.Text>{t('client.repas.choisirUnRestaurateur')}</Typography.Text>
        <Select
          style={{ width: '100%' }}
          loading={restaurateurs.isPending}
          value={restaurateurId}
          onChange={(v) => {
            setRestaurateurId(v)
            setQuantites({})
          }}
          options={restaurateurs.data?.map((r: RestaurateurActif) => ({ value: r.id, label: r.nom }))}
        />

        {restaurateur && (
          <Table
            rowKey="id"
            size="small"
            dataSource={restaurateur.carte.filter((p) => p.disponible)}
            pagination={false}
            locale={{ emptyText: <EtatVide titre={t('client.repas.carteVide')} /> }}
            columns={[
              { title: t('backoffice.restaurateurs.produit.nom'), dataIndex: 'nom' },
              { title: t('backoffice.restaurateurs.produit.prixAchat'), dataIndex: 'prix', render: (v: number) => formaterPrix(v) },
              {
                title: t('client.repas.quantite'),
                key: 'quantite',
                render: (_, produit) => (
                  <InputNumber
                    min={0}
                    precision={0}
                    value={quantites[produit.id] ?? 0}
                    onChange={(v) => setQuantites((q) => ({ ...q, [produit.id]: v ?? 0 }))}
                    aria-label={t('client.repas.quantiteDe', { produit: produit.nom })}
                  />
                ),
              },
            ]}
          />
        )}

        {restaurateur && (
          <>
            <Typography.Text>{t('client.repas.modeDeReglement')}</Typography.Text>
            <Select<ModeDeReglementRepas>
              style={{ width: '100%' }}
              value={modeReglement}
              onChange={setModeReglement}
              options={[
                { value: 'note_du_sejour', label: t('client.repas.modeNoteDuSejour') },
                { value: 'en_ligne', label: t('client.repas.modeEnLigne') },
                { value: 'a_terme', label: t('client.repas.modeATerme') },
              ]}
            />
            <Button type="primary" loading={commander.isPending} disabled={!auMoinsUneLigne} onClick={() => commander.mutate()}>
              {t('client.repas.envoyerLaCommande')}
            </Button>
          </>
        )}

        {message && <Alert type="success" showIcon title={message} />}
        {erreur && <Alert type="error" showIcon title={erreur} />}
      </Space>
    </div>
  )
}
