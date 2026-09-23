import { tonsSemantiques } from './jetons'

type Tonalite = keyof typeof tonsSemantiques

/** Domaines couverts par la table de correspondance ci-dessous — un par machine à états back-end connue côté front. */
export type DomaineStatut =
  | 'sejour'
  | 'reglement'
  | 'devis'
  | 'publicationLogement'
  | 'disponibiliteResidence'
  | 'compte'
  | 'demandeATerme'
  | 'piece'
  | 'actif'
  | 'transmissionFne'
  | 'transfert'
  | 'commandeRepas'
  | 'mission'
  | 'ticketAssistance'
  | 'reclamation'
  | 'demandeAnnulation'

/**
 * Langage visuel unique des statuts métier (CdC, brief refonte §31/§43) : même couleur/forme
 * partout où un statut est affiché, quel que soit l'écran. Les clés reprennent EXACTEMENT les
 * valeurs des enums PHP (api/app/Domain/...) — jamais les libellés traduits, qui restent fournis
 * par l'API (`*_libelle`) et passés en prop `libelle`. Consolidé ici (propagation, 23/09/2026)
 * depuis plusieurs tables `COULEUR_STATUT`/`COULEUR_ETAT` dupliquées écran par écran, qui avaient
 * fini par diverger entre elles (ex. `etat_publication` coloré différemment sur la fiche résidence
 * et la fiche logement pour la même valeur `suspendu`, `prix_a_negocier` oublié d'une des tables).
 */
const TONALITE_PAR_CODE: Record<DomaineStatut, Record<string, Tonalite>> = {
  sejour: {
    demande: 'information',
    confirme: 'succes',
    arrive: 'succes',
    parti: 'neutre',
    cloture: 'neutre',
    annule: 'erreur',
    no_show: 'erreur',
  },
  reglement: {
    en_attente: 'alerte',
    a_payer: 'alerte',
    preuve_jointe: 'information',
    effectue: 'succes',
    rejete: 'erreur',
  },
  devis: {
    en_attente: 'alerte',
    transforme: 'succes',
    archive: 'neutre',
  },
  /** api/app/Domain/Catalogue/Enums/EtatPublication.php */
  publicationLogement: {
    brouillon: 'neutre',
    en_attente: 'alerte',
    prix_a_negocier: 'alerte',
    refuse: 'erreur',
    suspendu: 'erreur',
    publie: 'succes',
  },
  /** api/app/Domain/Catalogue/Enums/Disponibilite.php — « occupée » = retirée par le propriétaire, pas une erreur. */
  disponibiliteResidence: {
    disponible: 'succes',
    occupee: 'alerte',
  },
  /** api/app/Domain/Comptes/Enums/StatutCompte.php */
  compte: {
    en_attente: 'alerte',
    actif: 'succes',
    bloque: 'erreur',
  },
  /** api/app/Domain/Sejours/Enums/StatutDemandeATerme.php */
  demandeATerme: {
    aucune: 'neutre',
    en_attente: 'alerte',
    acceptee: 'succes',
    refusee: 'erreur',
  },
  /** api/app/Domain/Partenaires/Enums/StatutDePiece.php */
  piece: {
    en_attente: 'alerte',
    validee: 'succes',
    refusee: 'erreur',
  },
  /** Drapeau booléen générique (résidence/agence active, dossier complet…) — code synthétique, pas un enum back-end. */
  actif: {
    actif: 'succes',
    inactif: 'neutre',
  },
  /** api/app/Domain/Fiscalite/Enums/StatutDeTransmissionFne.php */
  transmissionFne: {
    a_transmettre: 'alerte',
    transmise: 'succes',
    refusee: 'erreur',
    non_configuree: 'neutre',
  },
  /** api/app/Domain/Transferts/Enums/EtatDuTransfert.php */
  transfert: {
    demande: 'information',
    affecte: 'alerte',
    termine: 'succes',
    annule: 'erreur',
  },
  /** api/app/Domain/Repas/Enums/EtatDeCommande.php */
  commandeRepas: {
    demande: 'information',
    confirmee: 'alerte',
    en_preparation: 'alerte',
    prete: 'alerte',
    en_livraison: 'alerte',
    livree: 'succes',
    annulee: 'erreur',
    refusee: 'erreur',
  },
  /** api/app/Domain/Exploitation/Enums/StatutDeMission.php */
  mission: {
    a_faire: 'alerte',
    en_cours: 'information',
    faite: 'succes',
  },
  /** api/app/Domain/Assistance/Enums/EtatDuTicket.php */
  ticketAssistance: {
    ouvert: 'alerte',
    en_cours: 'information',
    ferme: 'neutre',
  },
  /** api/app/Domain/Assistance/Enums/EtatDeLaReclamation.php (nom exact non vérifié, valeurs confirmées côté API) */
  reclamation: {
    ouverte: 'alerte',
    en_cours: 'information',
    fermee: 'neutre',
  },
  /** api/app/Domain/Sejours/Enums/EtatDeLaDemandeAnnulation.php */
  demandeAnnulation: {
    en_attente: 'alerte',
    acceptee: 'succes',
    rejetee: 'erreur',
  },
}

/** Table de correspondance réutilisée partout où un statut se rend autrement qu'en badge (ex. les barres du planning). */
export function tonaliteDuStatut(domaine: DomaineStatut, code: string): Tonalite {
  return TONALITE_PAR_CODE[domaine][code] ?? 'neutre'
}
