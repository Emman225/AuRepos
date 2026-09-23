import type { ThemeConfig } from 'antd'

/**
 * Charte graphique de la plateforme — source unique pour le web.
 * Les mêmes valeurs existent en variables CSS (index.css) et dans le
 * thème Flutter (mobile/packages/ui) : on change une couleur ici, on la
 * change là-bas.
 *
 * Règle de lisibilité : le sable est une SURFACE (cartes, accents), jamais
 * une couleur de texte sur fond clair — son contraste y est insuffisant.
 */
export const couleurs = {
  bleuNuit: '#1E3A5F', // principale : navigation, boutons, titres
  bleuNuitFonce: '#152B47', // survol et enfoncement des éléments bleu nuit
  sable: '#D9C3A5', // secondaire : cartes, accents, décor
  sableClair: '#EFE4D3', // surfaces douces, lignes survolées
  blancCasse: '#F8F7F4', // arrière-plans
  blanc: '#FFFFFF',
  texte: '#1F2A37',
  texteDiscret: '#5B6676',
  bordure: '#E4DED3',
  succes: '#2E7D5B',
  alerte: '#B7791F',
  erreur: '#B3261E',
  information: '#2B6CB0',
} as const

/**
 * Les huit états du planning d'occupation (CdC § 6.2).
 * Chaque état a une couleur de fond et une couleur de texte lisible dessus.
 */
export const etatsPlanning = {
  libre: { fond: '#FFFFFF', texte: '#1F2A37', libelle: 'Libre' },
  reserve: { fond: '#D9C3A5', texte: '#1E3A5F', libelle: 'Réservé' },
  occupe: { fond: '#1E3A5F', texte: '#FFFFFF', libelle: 'Occupé' },
  departAujourdhui: { fond: '#B7791F', texte: '#FFFFFF', libelle: 'Départ aujourd’hui' },
  enMenage: { fond: '#5BA8A0', texte: '#0F2E2B', libelle: 'En ménage' },
  enMaintenance: { fond: '#B3261E', texte: '#FFFFFF', libelle: 'En maintenance' },
  bloqueProprietaire: { fond: '#8A94A6', texte: '#FFFFFF', libelle: 'Bloqué propriétaire' },
  fermee: { fond: '#3A3F47', texte: '#FFFFFF', libelle: 'Occupée (fermée par le propriétaire)' },
} as const

/**
 * Échelles complètes 50→950, dérivées des couleurs imposées ci-dessus (§24 du
 * brief de refonte). `couleurs.bleuNuit`/`couleurs.sable` restent la référence
 * pour la compatibilité ascendante (tout le code existant les utilise déjà) ;
 * ces échelles s'y ajoutent pour construire badges/fonds/hovers sans jamais
 * inventer une teinte hors charte. primary-700 = bleuNuit, primary-800 =
 * bleuNuitFonce, secondary-400 = sable, secondary-200 = sableClair : les
 * valeurs déjà utilisées ailleurs dans le code sont réutilisées telles quelles.
 */
export const paletteBleuNuit = {
  50: '#EEF2F7',
  100: '#D8E1EC',
  200: '#B3C5DA',
  300: '#8AA5C4',
  400: '#6084AB',
  500: '#3E6690',
  600: '#2A4E76',
  700: couleurs.bleuNuit,
  800: couleurs.bleuNuitFonce,
  900: '#101F33',
  950: '#0A1420',
} as const

export const paletteSable = {
  50: '#FBF8F3',
  100: '#F5EEE3',
  200: couleurs.sableClair,
  300: '#E4D2B4',
  400: couleurs.sable,
  500: '#C7A97E',
  600: '#AE8B5A',
  700: '#8F6F42',
  800: '#6E5433',
  900: '#4D3A24',
  950: '#2E2216',
} as const

/** Gris neutre indépendant du bleu, pour texte secondaire / bordures / fonds hors charte principale. */
export const paletteNeutre = {
  50: '#F7F8FA',
  100: '#EEF0F3',
  200: '#DDE1E6',
  300: '#C3C9D1',
  400: '#9BA3B0',
  500: '#717A8A',
  600: '#545D6E',
  700: '#3D4455',
  800: '#272D3B',
  900: '#1A1F29',
  950: '#0F1319',
} as const

/**
 * Tons sémantiques à 3 niveaux (fond léger / bordure / texte fort), pour bâtir
 * badges et alertes cohérents. `texte` reprend exactement `couleurs.succes` etc.
 * — jamais une couleur seule ne porte l'information : toujours associée à un
 * libellé ou une icône (accessibilité, §30 du brief).
 */
export const tonsSemantiques = {
  succes: { fond: '#E7F4EE', bordure: '#B7DFCB', texte: couleurs.succes },
  alerte: { fond: '#FBF1E1', bordure: '#EACB94', texte: couleurs.alerte },
  erreur: { fond: '#FBEAE9', bordure: '#E9B3AF', texte: couleurs.erreur },
  information: { fond: '#E9F1FA', bordure: '#A9C8E8', texte: couleurs.information },
  neutre: { fond: paletteNeutre[100], bordure: paletteNeutre[300], texte: paletteNeutre[700] },
} as const

/** Échelle d'espacement commune (multiples de 4px) — sert de référence pour tous les nouveaux composants partagés. */
export const espacements = { xs: 4, sm: 8, md: 12, lg: 16, xl: 24, xxl: 32, xxxl: 48 } as const

export const rayons = { sm: 6, md: 8, lg: 12, xl: 16, pill: 999 } as const

export const ombres = {
  legere: '0 1px 2px rgba(30, 58, 95, 0.06)',
  moyenne: '0 4px 12px rgba(30, 58, 95, 0.10)',
  forte: '0 12px 32px rgba(30, 58, 95, 0.16)',
} as const

/** Échelle typographique métier (Inter) — distincte de `jetonsSitePublic` (Newsreader, site public uniquement). */
export const typographie = {
  h1: { fontSize: 30, fontWeight: 600, lineHeight: 1.25 },
  h2: { fontSize: 24, fontWeight: 600, lineHeight: 1.3 },
  h3: { fontSize: 20, fontWeight: 600, lineHeight: 1.35 },
  h4: { fontSize: 16, fontWeight: 600, lineHeight: 1.4 },
  corpsGrand: { fontSize: 16, fontWeight: 400, lineHeight: 1.5 },
  corps: { fontSize: 14, fontWeight: 400, lineHeight: 1.5 },
  corpsPetit: { fontSize: 13, fontWeight: 400, lineHeight: 1.45 },
  legende: { fontSize: 12, fontWeight: 400, lineHeight: 1.4 },
  etiquette: { fontSize: 12, fontWeight: 600, lineHeight: 1.3, letterSpacing: '0.01em' },
  /** Chiffres financiers : chasse fixe pour un alignement en colonnes (caisse, relevés). */
  numerique: { fontSize: 15, fontWeight: 600, lineHeight: 1.4, fontVariantNumeric: 'tabular-nums' as const },
} as const

export const themeAntd: ThemeConfig = {
  token: {
    colorPrimary: couleurs.bleuNuit,
    colorInfo: couleurs.information,
    colorSuccess: couleurs.succes,
    colorWarning: couleurs.alerte,
    colorError: couleurs.erreur,
    colorTextBase: couleurs.texte,
    colorBgLayout: couleurs.blancCasse,
    colorBgContainer: couleurs.blanc,
    colorBorderSecondary: couleurs.bordure,
    colorLink: couleurs.bleuNuit,
    borderRadius: 8,
    fontFamily: "'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif",
  },
  components: {
    Layout: {
      headerBg: couleurs.bleuNuit,
      siderBg: couleurs.bleuNuit,
      bodyBg: couleurs.blancCasse,
      footerBg: couleurs.blancCasse,
    },
    Menu: {
      darkItemBg: couleurs.bleuNuit,
      darkSubMenuItemBg: couleurs.bleuNuitFonce,
      darkItemSelectedBg: couleurs.sable,
      darkItemSelectedColor: couleurs.bleuNuit,
    },
    Table: {
      headerBg: couleurs.bleuNuit,
      headerColor: couleurs.blanc,
      headerSortActiveBg: couleurs.bleuNuitFonce,
      headerSortHoverBg: couleurs.bleuNuitFonce,
      rowHoverBg: couleurs.sableClair,
      borderColor: couleurs.bordure,
      headerBorderRadius: 10,
    },
    Modal: {
      headerBg: couleurs.bleuNuit,
      titleColor: couleurs.blanc,
      contentBg: couleurs.blanc,
      footerBg: couleurs.blanc,
      borderRadiusLG: 12,
    },
    Card: { colorBorderSecondary: couleurs.bordure, borderRadiusLG: 12 },
    Button: { borderRadius: 8, controlHeight: 38 },
    Input: { borderRadius: 8, controlHeight: 38 },
    Select: { borderRadius: 8, controlHeight: 38 },
    Tag: { borderRadiusSM: 6 },
  },
}
