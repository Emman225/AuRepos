import { lire } from '../../shared/api/client'
import type { CommissionApporteur, CompteursApporteur, Filleul } from './types'

export const compteursDeLApporteur = (): Promise<CompteursApporteur> => lire<CompteursApporteur>('/apporteur/tableau-de-bord')

export const mesFilleuls = (): Promise<Filleul[]> => lire<Filleul[]>('/apporteur/filleuls')

export const mesCommissions = (): Promise<CommissionApporteur[]> => lire<CommissionApporteur[]>('/apporteur/commissions')
