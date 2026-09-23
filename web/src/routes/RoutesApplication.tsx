import { Button, Result } from 'antd'
import { useTranslation } from 'react-i18next'
import { Link, Route, Routes } from 'react-router-dom'
import { ACCUEIL_PAR_ESPACE } from '../features/auth/espaces'
import { PageAccesRefuse } from '../features/auth/PageAccesRefuse'
import { PageConnexion } from '../features/auth/PageConnexion'
import { PageInscription } from '../features/auth/PageInscription'
import { PageMotDePasseOublie } from '../features/auth/PageMotDePasseOublie'
import { PageVerification } from '../features/auth/PageVerification'
import { RequireProfil } from '../features/auth/RequireProfil'
import type { Espace } from '../features/auth/types'
import { useDemarrageSession } from '../features/auth/useDemarrageSession'
import { PageTableauDeBord as PageTableauDeBordBackOffice } from '../features/back-office/PageTableauDeBord'
import { PageApporteurs } from '../features/back-office/apporteurs/PageApporteurs'
import { PageCaisse } from '../features/back-office/caisse/PageCaisse'
import { PageDetailLogement } from '../features/back-office/catalogue/PageDetailLogement'
import { PageDetailResidence } from '../features/back-office/catalogue/PageDetailResidence'
import { PageReferentielsCatalogue } from '../features/back-office/catalogue/PageReferentielsCatalogue'
import { PageResidences } from '../features/back-office/catalogue/PageResidences'
import { PageChauffeurs } from '../features/back-office/chauffeurs/PageChauffeurs'
import { PageClients } from '../features/back-office/clients/PageClients'
import { PageCommandesRepas } from '../features/back-office/commandes-repas/PageCommandesRepas'
import { PageDemandesAnnulation } from '../features/back-office/demandes-annulation/PageDemandesAnnulation'
import { PageFactures } from '../features/back-office/factures/PageFactures'
import { PageLivreurs } from '../features/back-office/livreurs/PageLivreurs'
import { PageMissions } from '../features/back-office/missions/PageMissions'
import { PageEquipe } from '../features/back-office/personnel/PageEquipe'
import { PageParametres } from '../features/back-office/parametres/PageParametres'
import { PagePlanning } from '../features/back-office/planning/PagePlanning'
import { PageDetailProprietaire } from '../features/back-office/proprietaires/PageDetailProprietaire'
import { PageProprietaires } from '../features/back-office/proprietaires/PageProprietaires'
import { PageReclamations } from '../features/back-office/reclamations/PageReclamations'
import { PageRestaurateurs } from '../features/back-office/restaurateurs/PageRestaurateurs'
import { PageDetailReservation } from '../features/back-office/reservations/PageDetailReservation'
import { PageReservations } from '../features/back-office/reservations/PageReservations'
import { PageTarification } from '../features/back-office/tarification/PageTarification'
import { PageTicketsAssistance } from '../features/back-office/tickets-assistance/PageTicketsAssistance'
import { PageTransferts } from '../features/back-office/transferts/PageTransferts'
import { PageDetailDevis } from '../features/client-espace/PageDetailDevis'
import { PageDetailSejour } from '../features/client-espace/PageDetailSejour'
import { PageMesDevis } from '../features/client-espace/PageMesDevis'
import { PageMesPaiements } from '../features/client-espace/PageMesPaiements'
import { PageMesSejours } from '../features/client-espace/PageMesSejours'
import { PageMonCompte } from '../features/client-espace/PageMonCompte'
import { PageTableauDeBord } from '../features/client-espace/PageTableauDeBord'
import { PagePaiementRetour } from '../features/reservation/PagePaiementRetour'
import { PageTunnelReservation } from '../features/reservation/PageTunnelReservation'
import { PageDetailSejour as PageDetailSejourAgent } from '../features/agent-terrain/PageDetailSejour'
import { PageMissions as PageMissionsAgent } from '../features/agent-terrain/PageMissions'
import { PageSejours as PageSejoursAgent } from '../features/agent-terrain/PageSejours'
import { PageTableauDeBord as PageTableauDeBordAgent } from '../features/agent-terrain/PageTableauDeBord'
import { PageCommissions as PageCommissionsApporteur } from '../features/apporteur/PageCommissions'
import { PageFilleuls as PageFilleulsApporteur } from '../features/apporteur/PageFilleuls'
import { PageTableauDeBord as PageTableauDeBordApporteur } from '../features/apporteur/PageTableauDeBord'
import { PageTableauDeBord as PageTableauDeBordAssistance } from '../features/assistance/PageTableauDeBord'
import { PageGains as PageGainsChauffeur } from '../features/chauffeur/PageGains'
import { PageTableauDeBord as PageTableauDeBordChauffeur } from '../features/chauffeur/PageTableauDeBord'
import { PageTransferts as PageTransfertsChauffeur } from '../features/chauffeur/PageTransferts'
import { PageVehicules as PageVehiculesChauffeur } from '../features/chauffeur/PageVehicules'
import { PageMesCourses as PageMesCoursesLivreur } from '../features/livreur/PageMesCourses'
import { PageMesGains as PageMesGainsLivreur } from '../features/livreur/PageMesGains'
import { PageTableauDeBord as PageTableauDeBordLivreur } from '../features/livreur/PageTableauDeBord'
import { PageDetailResidence as PageDetailResidenceProprietaire } from '../features/proprietaire/PageDetailResidence'
import { PageResidences as PageResidencesProprietaire } from '../features/proprietaire/PageResidences'
import { PageTableauDeBord as PageTableauDeBordProprietaire } from '../features/proprietaire/PageTableauDeBord'
import { PageMaCarte as PageMaCarteRestaurateur } from '../features/restaurateur/PageMaCarte'
import { PageMaDette as PageMaDetteRestaurateur } from '../features/restaurateur/PageMaDette'
import { PageMesCommandes as PageMesCommandesRestaurateur } from '../features/restaurateur/PageMesCommandes'
import { PageTableauDeBord as PageTableauDeBordRestaurateur } from '../features/restaurateur/PageTableauDeBord'
import { PageAccueil } from '../features/site-public/PageAccueil'
import { PageArticle } from '../features/site-public/PageArticle'
import { PageBlog } from '../features/site-public/PageBlog'
import { PageFicheLogement } from '../features/site-public/PageFicheLogement'
import { PageLegale } from '../features/site-public/PageLegale'
import { PageRecherche } from '../features/site-public/PageRecherche'
import { GabaritEspace } from '../shared/layouts/GabaritEspace'
import { GabaritSitePublic } from '../shared/layouts/GabaritSitePublic'
import { ECRANS_BACKOFFICE } from '../shared/layouts/menuBackOffice'
import { PageBientotDisponible } from './PageBientotDisponible'
import { TableauDeBordProvisoire } from './TableauDeBordProvisoire'

const ESPACES = Object.keys(ACCUEIL_PAR_ESPACE) as Espace[]
// Même restriction que les entrées de menu correspondantes, lue depuis la même liste que le menu
// (voir ECRANS_BACKOFFICE) plutôt que dupliquée ici.
const PROFILS_RESERVATIONS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'reservations')?.profils
const PROFILS_PLANNING = ECRANS_BACKOFFICE.find((e) => e.chemin === 'planning')?.profils
const PROFILS_CATALOGUE = ECRANS_BACKOFFICE.find((e) => e.chemin === 'catalogue')?.profils
const PROFILS_PROPRIETAIRES = ECRANS_BACKOFFICE.find((e) => e.chemin === 'proprietaires')?.profils
const PROFILS_APPORTEURS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'apporteurs')?.profils
const PROFILS_TRANSFERTS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'transferts')?.profils
const PROFILS_CHAUFFEURS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'chauffeurs')?.profils
const PROFILS_RESTAURATEURS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'restaurateurs')?.profils
const PROFILS_COMMANDES_REPAS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'commandes-repas')?.profils
const PROFILS_LIVREURS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'livreurs')?.profils
const PROFILS_TARIFICATION = ECRANS_BACKOFFICE.find((e) => e.chemin === 'tarification')?.profils
const PROFILS_CAISSE = ECRANS_BACKOFFICE.find((e) => e.chemin === 'caisse')?.profils
const PROFILS_CLIENTS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'clients')?.profils
const PROFILS_FACTURES = ECRANS_BACKOFFICE.find((e) => e.chemin === 'factures')?.profils
const PROFILS_PERSONNEL = ECRANS_BACKOFFICE.find((e) => e.chemin === 'personnel')?.profils
const PROFILS_PARAMETRES = ECRANS_BACKOFFICE.find((e) => e.chemin === 'parametres')?.profils
const PROFILS_MISSIONS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'missions')?.profils
const PROFILS_TICKETS_ASSISTANCE = ECRANS_BACKOFFICE.find((e) => e.chemin === 'tickets-assistance')?.profils
const PROFILS_RECLAMATIONS = ECRANS_BACKOFFICE.find((e) => e.chemin === 'reclamations')?.profils
const PROFILS_DEMANDES_ANNULATION = ECRANS_BACKOFFICE.find((e) => e.chemin === 'demandes-annulation')?.profils

function PageIntrouvable() {
  const { t } = useTranslation()
  return (
    <Result
      status="404"
      title="404"
      subTitle={t('erreurs.pageIntrouvable')}
      extra={
        <Link to="/">
          <Button type="primary">{t('erreurs.retourAccueil')}</Button>
        </Link>
      }
    />
  )
}

export function RoutesApplication() {
  const { t } = useTranslation()
  useDemarrageSession()

  return (
    <Routes>
      <Route element={<GabaritSitePublic />}>
        <Route path="/" element={<PageAccueil />} />
        <Route path="/recherche" element={<PageRecherche />} />
        <Route path="/logements/:reference" element={<PageFicheLogement />} />
        <Route path="/blog" element={<PageBlog />} />
        <Route path="/blog/:slug" element={<PageArticle />} />
        <Route
          path="/logements/:reference/reserver"
          element={
            <RequireProfil espace="client">
              <PageTunnelReservation />
            </RequireProfil>
          }
        />
        <Route
          path="/paiement/retour/:reference"
          element={
            <RequireProfil espace="client">
              <PagePaiementRetour />
            </RequireProfil>
          }
        />
        <Route path="/conditions-generales" element={<PageLegale cle="conditions.cgv" titre={t('legal.cgv')} />} />
        <Route
          path="/confidentialite"
          element={<PageLegale cle="conditions.confidentialite" titre={t('legal.confidentialite')} />}
        />
      </Route>
      <Route path="/connexion" element={<PageConnexion />} />
      <Route path="/inscription" element={<PageInscription />} />
      <Route path="/verification" element={<PageVerification />} />
      <Route path="/mot-de-passe-oublie" element={<PageMotDePasseOublie />} />
      <Route path="/acces-refuse" element={<PageAccesRefuse />} />

      {/* Une branche gardée par espace. Les écrans de chaque espace s'y ajouteront lot après lot. */}
      {ESPACES.map((espace) => (
        <Route
          key={espace}
          path={ACCUEIL_PAR_ESPACE[espace]}
          element={
            <RequireProfil espace={espace}>
              <GabaritEspace titre={t(`espaces.${espace}`)} espace={espace} />
            </RequireProfil>
          }
        >
          <Route
            index
            element={
              espace === 'client' ? (
                <PageTableauDeBord />
              ) : espace === 'backoffice' ? (
                <PageTableauDeBordBackOffice />
              ) : espace === 'proprietaire' ? (
                <PageTableauDeBordProprietaire />
              ) : espace === 'apporteur' ? (
                <PageTableauDeBordApporteur />
              ) : espace === 'restaurateur' ? (
                <PageTableauDeBordRestaurateur />
              ) : espace === 'livreur' ? (
                <PageTableauDeBordLivreur />
              ) : espace === 'chauffeur' ? (
                <PageTableauDeBordChauffeur />
              ) : espace === 'agent' ? (
                <PageTableauDeBordAgent />
              ) : espace === 'assistance' ? (
                <PageTableauDeBordAssistance />
              ) : (
                <TableauDeBordProvisoire />
              )
            }
          />
          {/* Le menu (GabaritEspace) et les routes viennent de la MÊME liste : impossible qu'une
              entrée de menu pointe vers une adresse qui n'existe pas, ou l'inverse. « Réservations »
              et « Catalogue » ont leur écran réel (P1-BO-02, P1-BO-03) ; les autres restent
              « bientôt disponible » lot après lot. */}
          {espace === 'backoffice' && (
            <>
              <Route
                path="reservations"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_RESERVATIONS}>
                    <PageReservations />
                  </RequireProfil>
                }
              />
              <Route
                path="reservations/:id"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_RESERVATIONS}>
                    <PageDetailReservation />
                  </RequireProfil>
                }
              />
              <Route
                path="planning"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_PLANNING}>
                    <PagePlanning />
                  </RequireProfil>
                }
              />
              <Route
                path="catalogue"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_CATALOGUE}>
                    <PageResidences />
                  </RequireProfil>
                }
              />
              <Route
                path="catalogue/referentiels"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_CATALOGUE}>
                    <PageReferentielsCatalogue />
                  </RequireProfil>
                }
              />
              <Route
                path="catalogue/:id"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_CATALOGUE}>
                    <PageDetailResidence />
                  </RequireProfil>
                }
              />
              <Route
                path="catalogue/:id/logements/:logementId"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_CATALOGUE}>
                    <PageDetailLogement />
                  </RequireProfil>
                }
              />
              <Route
                path="proprietaires"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_PROPRIETAIRES}>
                    <PageProprietaires />
                  </RequireProfil>
                }
              />
              <Route
                path="proprietaires/:id"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_PROPRIETAIRES}>
                    <PageDetailProprietaire />
                  </RequireProfil>
                }
              />
              <Route
                path="apporteurs"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_APPORTEURS}>
                    <PageApporteurs />
                  </RequireProfil>
                }
              />
              <Route
                path="transferts"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_TRANSFERTS}>
                    <PageTransferts />
                  </RequireProfil>
                }
              />
              <Route
                path="chauffeurs"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_CHAUFFEURS}>
                    <PageChauffeurs />
                  </RequireProfil>
                }
              />
              <Route
                path="restaurateurs"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_RESTAURATEURS}>
                    <PageRestaurateurs />
                  </RequireProfil>
                }
              />
              <Route
                path="commandes-repas"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_COMMANDES_REPAS}>
                    <PageCommandesRepas />
                  </RequireProfil>
                }
              />
              <Route
                path="livreurs"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_LIVREURS}>
                    <PageLivreurs />
                  </RequireProfil>
                }
              />
              <Route
                path="tarification"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_TARIFICATION}>
                    <PageTarification />
                  </RequireProfil>
                }
              />
              <Route
                path="caisse"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_CAISSE}>
                    <PageCaisse />
                  </RequireProfil>
                }
              />
              <Route
                path="clients"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_CLIENTS}>
                    <PageClients />
                  </RequireProfil>
                }
              />
              <Route
                path="factures"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_FACTURES}>
                    <PageFactures />
                  </RequireProfil>
                }
              />
              <Route
                path="personnel"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_PERSONNEL}>
                    <PageEquipe />
                  </RequireProfil>
                }
              />
              <Route
                path="parametres"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_PARAMETRES}>
                    <PageParametres />
                  </RequireProfil>
                }
              />
              <Route
                path="missions"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_MISSIONS}>
                    <PageMissions />
                  </RequireProfil>
                }
              />
              <Route
                path="tickets-assistance"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_TICKETS_ASSISTANCE}>
                    <PageTicketsAssistance />
                  </RequireProfil>
                }
              />
              <Route
                path="reclamations"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_RECLAMATIONS}>
                    <PageReclamations />
                  </RequireProfil>
                }
              />
              <Route
                path="demandes-annulation"
                element={
                  <RequireProfil espace="backoffice" profils={PROFILS_DEMANDES_ANNULATION}>
                    <PageDemandesAnnulation />
                  </RequireProfil>
                }
              />
              {ECRANS_BACKOFFICE.filter(
                (e) =>
                  ![
                    'reservations',
                    'planning',
                    'catalogue',
                    'proprietaires',
                    'apporteurs',
                    'transferts',
                    'chauffeurs',
                    'restaurateurs',
                    'commandes-repas',
                    'livreurs',
                    'tarification',
                    'caisse',
                    'clients',
                    'factures',
                    'personnel',
                    'parametres',
                    'missions',
                    'tickets-assistance',
                    'reclamations',
                    'demandes-annulation',
                  ].includes(e.chemin),
              ).map((ecran) => (
                <Route
                  key={ecran.chemin}
                  path={ecran.chemin}
                  element={
                    <RequireProfil espace="backoffice" profils={ecran.profils}>
                      <PageBientotDisponible titre={t(ecran.cle)} />
                    </RequireProfil>
                  }
                />
              ))}
            </>
          )}
          {espace === 'client' && (
            <>
              <Route path="sejours" element={<PageMesSejours />} />
              <Route path="sejours/:reference" element={<PageDetailSejour />} />
              <Route path="devis" element={<PageMesDevis />} />
              <Route path="devis/:reference" element={<PageDetailDevis />} />
              <Route path="paiements" element={<PageMesPaiements />} />
              <Route path="compte" element={<PageMonCompte />} />
            </>
          )}
          {espace === 'proprietaire' && (
            <>
              <Route path="residences" element={<PageResidencesProprietaire />} />
              <Route path="residences/:id" element={<PageDetailResidenceProprietaire />} />
            </>
          )}
          {espace === 'apporteur' && (
            <>
              <Route path="filleuls" element={<PageFilleulsApporteur />} />
              <Route path="commissions" element={<PageCommissionsApporteur />} />
            </>
          )}
          {espace === 'restaurateur' && (
            <>
              <Route path="carte" element={<PageMaCarteRestaurateur />} />
              <Route path="commandes" element={<PageMesCommandesRestaurateur />} />
              <Route path="dette" element={<PageMaDetteRestaurateur />} />
            </>
          )}
          {espace === 'livreur' && (
            <>
              <Route path="courses" element={<PageMesCoursesLivreur />} />
              <Route path="gains" element={<PageMesGainsLivreur />} />
            </>
          )}
          {espace === 'chauffeur' && (
            <>
              <Route path="transferts" element={<PageTransfertsChauffeur />} />
              <Route path="gains" element={<PageGainsChauffeur />} />
              <Route path="vehicules" element={<PageVehiculesChauffeur />} />
            </>
          )}
          {espace === 'agent' && (
            <>
              <Route path="sejours" element={<PageSejoursAgent />} />
              <Route path="sejours/:id" element={<PageDetailSejourAgent />} />
              <Route path="missions" element={<PageMissionsAgent />} />
            </>
          )}
          <Route path="*" element={<PageIntrouvable />} />
        </Route>
      ))}

      <Route path="*" element={<PageIntrouvable />} />
    </Routes>
  )
}
