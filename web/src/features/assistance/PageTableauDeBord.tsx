import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Alert, Button, Drawer, Input, Select, Space, Table, Typography } from 'antd'
import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ErreurApi } from '../../shared/api/client'
import { EnTeteDePage } from '../../shared/composants/EnTeteDePage'
import { EtatVide } from '../../shared/composants/EtatVide'
import { StatutBadge } from '../../shared/composants/StatutBadge'
import { couleurs } from '../../shared/theme/jetons'
import { useSession } from '../auth/session'
import { fermerLeTicket, mesTickets, repondreAuTicket } from './api'
import type { EtatDuTicket, TicketAssistance } from './types'

const ETATS: EtatDuTicket[] = ['ouvert', 'en_cours', 'ferme']

/**
 * Espace assistance (self-service, CdC § 6.1, P2-AST-01) : la file de tickets EST le tableau de
 * bord — un seul écran suffit (pas de sous-menu, voir menuAssistance.tsx), la liste ouvre un
 * tiroir de détail avec réponse et fermeture. Consultation seule côté back office
 * (features/back-office/tickets-assistance) ; ici, l'espace agit.
 */
export function PageTableauDeBord() {
  const { t } = useTranslation()
  const utilisateur = useSession((s) => s.utilisateur)
  const queryClient = useQueryClient()
  const [statut, setStatut] = useState<EtatDuTicket | undefined>(undefined)
  const [ticketOuvert, setTicketOuvert] = useState<TicketAssistance | null>(null)
  const [reponse, setReponse] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  const filtres = { statut }
  const tickets = useQuery({ queryKey: ['assistance', 'tickets', filtres], queryFn: () => mesTickets(filtres) })
  const invalider = () => queryClient.invalidateQueries({ queryKey: ['assistance', 'tickets'] })

  const ouvrir = (ticket: TicketAssistance) => {
    setTicketOuvert(ticket)
    setReponse('')
    setErreur(null)
  }

  const repondre = useMutation({
    mutationFn: () => repondreAuTicket(ticketOuvert!.id, reponse),
    onSuccess: (mis_a_jour) => {
      setTicketOuvert(mis_a_jour)
      setReponse('')
      setErreur(null)
      void invalider()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  const fermer = useMutation({
    mutationFn: () => fermerLeTicket(ticketOuvert!.id, reponse || undefined),
    onSuccess: () => {
      setTicketOuvert(null)
      setReponse('')
      setErreur(null)
      void invalider()
    },
    onError: (e) => setErreur(e instanceof ErreurApi ? e.message : t('tunnel.erreurGenerique')),
  })

  if (!utilisateur) return null

  return (
    <div>
      <EnTeteDePage titre={t('espace.bienvenue', { nom: utilisateur.prenoms ?? utilisateur.nom })} description={t('assistance.description')} />

      <Space wrap style={{ marginBottom: 16 }}>
        <Select
          style={{ width: 200 }}
          allowClear
          placeholder={t('backoffice.ticketsAssistance.tousLesStatuts')}
          value={statut}
          onChange={setStatut}
          options={ETATS.map((e) => ({ value: e, label: t(`backoffice.ticketsAssistance.statuts.${e}`) }))}
        />
      </Space>

      <Table<TicketAssistance>
        rowKey="id"
        loading={tickets.isPending}
        dataSource={tickets.data}
        pagination={false}
        locale={{ emptyText: <EtatVide titre={t('backoffice.ticketsAssistance.aucun')} /> }}
        onRow={(tk) => ({ style: { cursor: 'pointer' }, onClick: () => ouvrir(tk) })}
        columns={[
          { title: t('backoffice.ticketsAssistance.sejour'), render: (_, tk) => tk.sejour?.reference ?? '—' },
          { title: t('backoffice.reservations.client'), dataIndex: 'client' },
          { title: t('backoffice.ticketsAssistance.sujet'), dataIndex: 'sujet' },
          {
            title: t('backoffice.clients.statut'),
            render: (_, tk) => <StatutBadge domaine="ticketAssistance" code={tk.statut} libelle={tk.statut_libelle} />,
          },
          { title: t('backoffice.ticketsAssistance.creeLe'), dataIndex: 'created_at', render: (v: string | null) => v ?? '—' },
        ]}
      />

      <Drawer title={ticketOuvert?.sujet} open={ticketOuvert !== null} onClose={() => setTicketOuvert(null)} width={420} destroyOnHidden>
        {ticketOuvert && (
          <>
            <Space style={{ marginBottom: 12 }}>
              <StatutBadge domaine="ticketAssistance" code={ticketOuvert.statut} libelle={ticketOuvert.statut_libelle} />
              {ticketOuvert.sejour && <Typography.Text type="secondary">{ticketOuvert.sejour.reference}</Typography.Text>}
            </Space>
            <Typography.Paragraph>
              <Typography.Text strong>{t('backoffice.reservations.client')} : </Typography.Text>
              <Typography.Text>{ticketOuvert.client}</Typography.Text>
            </Typography.Paragraph>
            <Typography.Paragraph style={{ background: couleurs.sableClair, padding: 12, borderRadius: 8 }}>
              {ticketOuvert.message}
            </Typography.Paragraph>

            {ticketOuvert.reponse && (
              <Typography.Paragraph type="secondary">
                <Typography.Text strong>{t('backoffice.ticketsAssistance.reponse')} : </Typography.Text>
                <Typography.Text type="secondary">{ticketOuvert.reponse}</Typography.Text>
              </Typography.Paragraph>
            )}

            {ticketOuvert.statut !== 'ferme' && (
              <>
                <Typography.Text>{t('assistance.votreReponse')}</Typography.Text>
                <Input.TextArea
                  style={{ marginTop: 4, marginBottom: 12 }}
                  rows={4}
                  value={reponse}
                  onChange={(e) => setReponse(e.target.value)}
                  aria-label={t('assistance.votreReponse')}
                />
                {erreur && <Alert style={{ marginBottom: 12 }} type="error" showIcon title={erreur} />}
                <Space>
                  <Button type="primary" loading={repondre.isPending} disabled={reponse.trim().length < 5} onClick={() => repondre.mutate()}>
                    {t('assistance.repondre')}
                  </Button>
                  <Button loading={fermer.isPending} onClick={() => fermer.mutate()}>
                    {t('assistance.fermer')}
                  </Button>
                </Space>
              </>
            )}
          </>
        )}
      </Drawer>
    </div>
  )
}
