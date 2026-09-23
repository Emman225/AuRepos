import {
  CalendarOutlined,
  CarOutlined,
  CloseCircleOutlined,
  CoffeeOutlined,
  CustomerServiceOutlined,
  FileTextOutlined,
  HomeOutlined,
  IdcardOutlined,
  MessageOutlined,
  SafetyCertificateOutlined,
  ScheduleOutlined,
  SettingOutlined,
  ShoppingCartOutlined,
  SolutionOutlined,
  TagsOutlined,
  TeamOutlined,
  ToolOutlined,
  UsergroupAddOutlined,
  UserOutlined,
  WalletOutlined,
} from '@ant-design/icons'
import type { ReactNode } from 'react'
import type { Profil } from '../../features/auth/types'

export interface EcranBackOffice {
  /** Chemin relatif à la racine de l'espace (/admin). */
  chemin: string
  /** Clé i18n du libellé de menu. */
  cle: string
  icone: ReactNode
  /** Restriction en plus de l'espace « backoffice » ; absent = tous les profils du back office. */
  profils?: Profil[]
}

const ADMINISTRATEURS: Profil[] = ['administrateur', 'super_administrateur']
const GESTION_QUOTIDIENNE: Profil[] = [...ADMINISTRATEURS, 'gestionnaire']

/**
 * Menu latéral du back office, filtré par profil (CdC § 3) : la gouvernante et l'agent
 * d'assistance appartiennent à l'espace « backoffice » mais leurs écrans arrivent au lot 2
 * (P2-MEN-*, P2-AST-*) — ils n'ont donc pour l'instant que le tableau de bord, commun à tous.
 * Chaque écran existe déjà comme route (page « bientôt disponible ») : c'est le futur écran
 * réel (P1-BO-0x) qui remplacera son contenu, pas son entrée de menu ni sa restriction.
 */
export const ECRANS_BACKOFFICE: EcranBackOffice[] = [
  {
    chemin: 'reservations',
    cle: 'backoffice.menu.reservations',
    icone: <CalendarOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    chemin: 'planning',
    cle: 'backoffice.menu.planning',
    icone: <ScheduleOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    chemin: 'missions',
    cle: 'backoffice.menu.missions',
    icone: <ToolOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  { chemin: 'catalogue', cle: 'backoffice.menu.catalogue', icone: <HomeOutlined />, profils: GESTION_QUOTIDIENNE },
  {
    chemin: 'proprietaires',
    cle: 'backoffice.menu.proprietaires',
    icone: <TeamOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    chemin: 'apporteurs',
    cle: 'backoffice.menu.apporteurs',
    icone: <UsergroupAddOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    chemin: 'transferts',
    cle: 'backoffice.menu.transferts',
    icone: <SolutionOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    chemin: 'chauffeurs',
    cle: 'backoffice.menu.chauffeurs',
    icone: <SafetyCertificateOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    // Tarification (grille, pourcentage entreprise, prix négociés, codes promo, référentiels)
    // est réservée aux administrateurs côté API (routes/api_v1/backoffice.php) : la gouvernante
    // « gestionnaire » n'a accès à aucun de ces points d'entrée, contrairement aux autres écrans
    // de gestion quotidienne.
    chemin: 'tarification',
    cle: 'backoffice.menu.tarification',
    icone: <TagsOutlined />,
    profils: ADMINISTRATEURS,
  },
  {
    chemin: 'restaurateurs',
    cle: 'backoffice.menu.restaurateurs',
    icone: <CoffeeOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    chemin: 'commandes-repas',
    cle: 'backoffice.menu.commandesRepas',
    icone: <ShoppingCartOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  {
    chemin: 'livreurs',
    cle: 'backoffice.menu.livreurs',
    icone: <CarOutlined />,
    profils: GESTION_QUOTIDIENNE,
  },
  { chemin: 'caisse', cle: 'backoffice.menu.caisse', icone: <WalletOutlined />, profils: GESTION_QUOTIDIENNE },
  { chemin: 'clients', cle: 'backoffice.menu.clients', icone: <UserOutlined />, profils: GESTION_QUOTIDIENNE },
  { chemin: 'factures', cle: 'backoffice.menu.factures', icone: <FileTextOutlined />, profils: ADMINISTRATEURS },
  { chemin: 'personnel', cle: 'backoffice.menu.personnel', icone: <IdcardOutlined />, profils: ADMINISTRATEURS },
  {
    chemin: 'demandes-annulation',
    cle: 'backoffice.menu.demandesAnnulation',
    icone: <CloseCircleOutlined />,
    profils: ADMINISTRATEURS,
  },
  {
    chemin: 'reclamations',
    cle: 'backoffice.menu.reclamations',
    icone: <MessageOutlined />,
    profils: ADMINISTRATEURS,
  },
  {
    chemin: 'tickets-assistance',
    cle: 'backoffice.menu.ticketsAssistance',
    icone: <CustomerServiceOutlined />,
    profils: ADMINISTRATEURS,
  },
  { chemin: 'parametres', cle: 'backoffice.menu.parametres', icone: <SettingOutlined />, profils: ADMINISTRATEURS },
]
