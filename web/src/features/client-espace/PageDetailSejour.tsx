import { useQueryClient, useMutation, useQuery } from '@tanstack/react-query'
import { Alert, Button, Form, Input, InputNumber, Select, Skeleton, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Link, useParams } from 'react-router-dom'
import { ErreurApi } from '../../shared/api/client'
import { EtatVide } from '../../shared/composants/EtatVide'
import { Modal } from '../../shared/composants/PopupModal'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { useConfirmerAction } from '../../shared/composants/confirmer'
import { formaterDate } from '../../shared/format/date'
import { formaterPrix } from '../../shared/format/devise'
import { couleurs } from '../../shared/theme/jetons'
import { ResumeDevis } from '../reservation/ResumeDevis'
import {
  annulerMonSejour,
  commanderUnRepas,
  creerUneReclamation,
  demanderLAnnulationDeMonSejour,
  mesCommandesRepas,
  mesReclamations,
  mesTicketsAssistance,
  mesTransfertsDuSejour,
  monSejour,
  ouvrirUnTicketAssistance,
  restaurateursActifs,
} from './api'
import { FormulaireDemandeTransfert } from './FormulaireDemandeTransfert'
import type { CommandeRepas, ModeDeReglementRepas, Reclamation, RestaurateurActif, TicketAssistance, TransfertClient } from './types'

const MOTIF_ANNULATION_MIN = 5
const MOTIF_RECLAMATION_MIN = 15

/** Espace client › Détail d'un séjour (CdC § 5.3) : code d'arrivée et adresse une fois confirmé. */
export function PageDetailSejour() {
  const { t } = useTranslation()
  const { reference } = useParams<{ reference: string }>()
  const queryClient = useQueryClient()
  const confirmer = useConfirmerAction()
  const [erreur, setErreur] = useState<string | null>(null)
  const [messageAnnulation, setMessageAnnulation] = useState<string | null>(null)
  const [demandeAnnulationOuverte, setDemandeAnnulationOuverte] = useState(false)

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

      {s.etat === 'arrive' && <SectionTicketsAssistance reference={s.reference} />}

      {(s.etat === 'parti' || s.etat === 'cloture') && <SectionReclamations reference={s.reference} />}

      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
      {messageAnnulation && <Alert style={{ marginTop: 16 }} type="success" showIcon title={messageAnnulation} />}

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

      {(s.etat === 'confirme' || s.etat === 'arrive') && (
        <Space style={{ marginTop: 24 }}>
          <Button danger onClick={() => setDemandeAnnulationOuverte(true)}>
            {t('client.annulation.demanderLAnnulation')}
          </Button>
        </Space>
      )}

      <ModaleDemandeAnnulation
        ouvert={demandeAnnulationOuverte}
        reference={s.reference}
        onFermer={() => setDemandeAnnulationOuverte(false)}
        onEnvoyee={() => {
          setDemandeAnnulationOuverte(false)
          setMessageAnnulation(t('client.annulation.envoyee'))
        }}
      />
    </div>
  )
}

/** Un séjour confirmé (ou déjà arrivé) ne s'annule plus d'un geste : le client DEMANDE, motivé, la réception instruit (P2-SEJ-06). */
function ModaleDemandeAnnulation({
  ouvert,
  reference,
  onFermer,
  onEnvoyee,
}: {
  ouvert: boolean
  reference: string
  onFermer: () => void
  onEnvoyee: () => void
}) {
  const { t } = useTranslation()
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const envoi = useMutation({
    mutationFn: () => demanderLAnnulationDeMonSejour(reference, motif),
    onSuccess: () => {
      setMotif('')
      setErreur(null)
      onEnvoyee()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const motifTropCourt = motif.trim().length < MOTIF_ANNULATION_MIN

  return (
    <Modal
      title={t('client.annulation.demanderLAnnulation')}
      open={ouvert}
      onCancel={() => {
        setMotif('')
        setErreur(null)
        onFermer()
      }}
      onOk={() => envoi.mutate()}
      confirmLoading={envoi.isPending}
      okText={t('client.annulation.envoyer')}
      cancelText={t('listes.confirmation.annuler')}
      okButtonProps={{ disabled: motifTropCourt, danger: true }}
      destroyOnHidden
      width={480}
    >
      <Form layout="vertical">
        <Form.Item label={t('client.annulation.motif')} htmlFor="champ-motif-demande-annulation" required>
          <Input.TextArea
            id="champ-motif-demande-annulation"
            rows={4}
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
            aria-label={t('client.annulation.motif')}
          />
        </Form.Item>
        {motifTropCourt && (
          <Typography.Text style={{ color: couleurs.texteDiscret, fontSize: 12 }}>
            {t('client.annulation.motifMinimum', { count: MOTIF_ANNULATION_MIN })}
          </Typography.Text>
        )}
      </Form>
      {erreur && <Alert style={{ marginTop: 12 }} type="error" showIcon title={erreur} />}
    </Modal>
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

/** Tickets déjà ouverts sur ce séjour, et formulaire pour en ouvrir un nouveau (P2-AST-01, CdC § 6.1) — PENDANT un séjour en cours seulement. */
function SectionTicketsAssistance({ reference }: { reference: string }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const tickets = useQuery({ queryKey: ['client', 'sejours', reference, 'tickets-assistance'], queryFn: () => mesTicketsAssistance(reference) })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['client', 'sejours', reference, 'tickets-assistance'] })

  return (
    <div style={{ marginTop: 24 }}>
      <Typography.Title level={4}>{t('client.assistance.titre')}</Typography.Title>

      {tickets.isPending ? (
        <Skeleton active paragraph={{ rows: 2 }} />
      ) : !tickets.data || tickets.data.length === 0 ? (
        <EtatVide titre={t('client.assistance.aucun')} />
      ) : (
        <Space orientation="vertical" style={{ width: '100%', marginBottom: 16 }}>
          {tickets.data.map((tk: TicketAssistance) => (
            <div key={tk.id} style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <Space align="center">
                  <Typography.Text strong>{tk.sujet}</Typography.Text>
                  <StatutBadge domaine="ticketAssistance" code={tk.statut} libelle={tk.statut_libelle} />
                </Space>
              </div>
              <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, fontSize: 13, marginTop: 4 }}>{tk.message}</Typography.Text>
              {tk.statut === 'ferme' && tk.reponse && (
                <Alert
                  style={{ marginTop: 10 }}
                  type="info"
                  showIcon
                  title={
                    <Typography.Text>
                      <Typography.Text strong>{t('client.assistance.reponse')}</Typography.Text> {tk.reponse}
                    </Typography.Text>
                  }
                />
              )}
            </div>
          ))}
        </Space>
      )}

      <FormulaireNouveauTicket reference={reference} onEnvoye={() => void invalider()} />
    </div>
  )
}

function FormulaireNouveauTicket({ reference, onEnvoye }: { reference: string; onEnvoye: () => void }) {
  const { t } = useTranslation()
  const [sujet, setSujet] = useState('')
  const [message, setMessage] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [confirmation, setConfirmation] = useState<string | null>(null)

  const envoi = useMutation({
    mutationFn: () => ouvrirUnTicketAssistance(reference, { sujet, message }),
    onSuccess: () => {
      setSujet('')
      setMessage('')
      setErreur(null)
      setConfirmation(t('client.assistance.envoye'))
      onEnvoye()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const peutEnvoyer = sujet.trim().length > 0 && message.trim().length > 0

  return (
    <div style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: 16, marginTop: 16 }}>
      <Typography.Title level={5} style={{ marginTop: 0 }}>
        {t('client.assistance.nouveauTicket')}
      </Typography.Title>
      <Form layout="vertical">
        <Form.Item label={t('client.assistance.sujet')} htmlFor="champ-sujet-ticket" required>
          <Input
            id="champ-sujet-ticket"
            maxLength={150}
            showCount
            value={sujet}
            onChange={(e) => setSujet(e.target.value)}
            aria-label={t('client.assistance.sujet')}
          />
        </Form.Item>
        <Form.Item label={t('client.assistance.message')} htmlFor="champ-message-ticket" required>
          <Input.TextArea
            id="champ-message-ticket"
            rows={4}
            maxLength={2000}
            showCount
            value={message}
            onChange={(e) => setMessage(e.target.value)}
            aria-label={t('client.assistance.message')}
          />
        </Form.Item>
        <Button type="primary" loading={envoi.isPending} disabled={!peutEnvoyer} onClick={() => envoi.mutate()}>
          {t('client.assistance.envoyer')}
        </Button>
      </Form>

      {confirmation && <Alert style={{ marginTop: 16 }} type="success" showIcon title={confirmation} />}
      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
    </div>
  )
}

/** Réclamations déjà soulevées sur ce séjour, et formulaire pour en soulever une nouvelle (P2-AST-01, CdC § 6.1) — APRÈS un séjour terminé seulement. */
function SectionReclamations({ reference }: { reference: string }) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const reclamations = useQuery({ queryKey: ['client', 'sejours', reference, 'reclamations'], queryFn: () => mesReclamations(reference) })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['client', 'sejours', reference, 'reclamations'] })

  return (
    <div style={{ marginTop: 24 }}>
      <Typography.Title level={4}>{t('client.reclamations.titre')}</Typography.Title>

      {reclamations.isPending ? (
        <Skeleton active paragraph={{ rows: 2 }} />
      ) : !reclamations.data || reclamations.data.length === 0 ? (
        <EtatVide titre={t('client.reclamations.aucune')} />
      ) : (
        <Space orientation="vertical" style={{ width: '100%', marginBottom: 16 }}>
          {reclamations.data.map((r: Reclamation) => (
            <div key={r.id} style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: 12 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 12, flexWrap: 'wrap' }}>
                <StatutBadge domaine="reclamation" code={r.statut} libelle={r.statut_libelle} />
              </div>
              <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, fontSize: 13, marginTop: 4 }}>{r.motif}</Typography.Text>
              {r.statut === 'fermee' && r.reponse && (
                <Alert
                  style={{ marginTop: 10 }}
                  type="info"
                  showIcon
                  title={
                    <Typography.Text>
                      <Typography.Text strong>{t('client.reclamations.reponse')}</Typography.Text> {r.reponse}
                    </Typography.Text>
                  }
                />
              )}
              {r.statut === 'fermee' && r.avoir_montant != null && (
                <Alert
                  style={{ marginTop: 10 }}
                  type="success"
                  showIcon
                  title={t('client.reclamations.avoir', { montant: formaterPrix(r.avoir_montant) })}
                />
              )}
            </div>
          ))}
        </Space>
      )}

      <FormulaireNouvelleReclamation reference={reference} onEnvoyee={() => void invalider()} />
    </div>
  )
}

function FormulaireNouvelleReclamation({ reference, onEnvoyee }: { reference: string; onEnvoyee: () => void }) {
  const { t } = useTranslation()
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [confirmation, setConfirmation] = useState<string | null>(null)

  const envoi = useMutation({
    mutationFn: () => creerUneReclamation(reference, { motif }),
    onSuccess: () => {
      setMotif('')
      setErreur(null)
      setConfirmation(t('client.reclamations.envoyee'))
      onEnvoyee()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const motifTropCourt = motif.trim().length < MOTIF_RECLAMATION_MIN

  return (
    <div style={{ border: `1px solid ${couleurs.bordure}`, borderRadius: 8, padding: 16, marginTop: 16 }}>
      <Typography.Title level={5} style={{ marginTop: 0 }}>
        {t('client.reclamations.nouvelleReclamation')}
      </Typography.Title>
      <Form layout="vertical">
        <Form.Item label={t('client.reclamations.motif')} htmlFor="champ-motif-reclamation" required>
          <Input.TextArea
            id="champ-motif-reclamation"
            rows={4}
            maxLength={2000}
            showCount
            value={motif}
            onChange={(e) => setMotif(e.target.value)}
            aria-label={t('client.reclamations.motif')}
          />
        </Form.Item>
        <Typography.Text style={{ display: 'block', color: couleurs.texteDiscret, fontSize: 12, marginTop: -8, marginBottom: 12 }}>
          {t('client.reclamations.motifMinimum', { count: MOTIF_RECLAMATION_MIN, saisis: motif.trim().length })}
        </Typography.Text>
        <Button type="primary" loading={envoi.isPending} disabled={motifTropCourt} onClick={() => envoi.mutate()}>
          {t('client.reclamations.envoyer')}
        </Button>
      </Form>

      {confirmation && <Alert style={{ marginTop: 16 }} type="success" showIcon title={confirmation} />}
      {erreur && <Alert style={{ marginTop: 16 }} type="error" showIcon title={erreur} />}
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
