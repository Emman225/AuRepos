import { Helmet } from 'react-helmet-async'

interface Props {
  titre: string
  description: string
  chemin: string
  image?: string
  /** JSON-LD (schema.org) à injecter pour cette page : Organization, Product, BreadcrumbList… */
  donneesStructurees?: object
}

/**
 * Balises meta d'une page publique (titre, description, Open Graph, canonique) et,
 * quand fourni, ses données structurées schema.org (CdC § 12, P1-PUB-09). Un simple
 * SPA ne remplit jamais ces balises par défaut (elles restent identiques sur toutes
 * les pages sans ce composant) — voir le journal de décisions pour ce que cela couvre
 * réellement et ce qui reste hors de portée (pré-rendu serveur).
 */
export function BaliseSeo({ titre, description, chemin, image, donneesStructurees }: Props) {
  const url = typeof window === 'undefined' ? chemin : `${window.location.origin}${chemin}`
  // Repli sur le logo : un lien partagé sans photo (page légale, fiche sans image, article sans
  // illustration) affichait un aperçu sans aucune image plutôt que la marque.
  const imageAffichee = image ?? (typeof window === 'undefined' ? '/logo.png' : `${window.location.origin}/logo.png`)

  return (
    <Helmet>
      <title>{titre}</title>
      <meta name="description" content={description} />
      <link rel="canonical" href={url} />
      <meta property="og:type" content="website" />
      <meta property="og:title" content={titre} />
      <meta property="og:description" content={description} />
      <meta property="og:url" content={url} />
      <meta property="og:image" content={imageAffichee} />
      <meta name="twitter:card" content={image ? 'summary_large_image' : 'summary'} />
      <meta name="twitter:title" content={titre} />
      <meta name="twitter:description" content={description} />
      <meta name="twitter:image" content={imageAffichee} />
      {donneesStructurees && <script type="application/ld+json">{JSON.stringify(donneesStructurees)}</script>}
    </Helmet>
  )
}
