import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { lire } from '../../shared/api/client'
import { BaliseSeo } from './BaliseSeo'
import { SectionApplications } from './SectionApplications'
import { SectionCategories } from './SectionCategories'
import { SectionHero } from './SectionHero'
import { SectionMeilleuresResidences } from './SectionMeilleuresResidences'
import { SectionMisesEnAvant } from './SectionMisesEnAvant'
import { SectionPromotions } from './SectionPromotions'
import { SectionResidencesMisesEnAvant } from './SectionResidencesMisesEnAvant'
import { SectionTemoignages } from './SectionTemoignages'
import type { DonneesAccueil, TypeLogementChoix } from './types'

/** Page d'accueil du site public (CdC § 5.1). */
export function PageAccueil() {
  const { t } = useTranslation()
  const accueil = useQuery({ queryKey: ['accueil'], queryFn: () => lire<DonneesAccueil>('/accueil') })
  const categories = useQuery({
    queryKey: ['referentiels', 'types-logement'],
    queryFn: () => lire<TypeLogementChoix[]>('/referentiels/types-logement'),
  })

  return (
    <div style={{ width: '100%' }}>
      <BaliseSeo
        titre={`${t('marque')} — ${t('accueil.titre')}`}
        description={t('accueil.sousTitre')}
        chemin="/"
        donneesStructurees={{
          '@context': 'https://schema.org',
          '@type': 'LodgingBusiness',
          name: t('marque'),
          description: t('accueil.sousTitre'),
          url: typeof window === 'undefined' ? '/' : window.location.origin,
        }}
      />

      <SectionHero diapositives={accueil.data?.carrousel ?? []} />

      <div className="rythme-accueil" style={{ padding: '56px 24px 24px', maxWidth: 1120, margin: '0 auto', width: '100%' }}>
        <SectionMisesEnAvant logements={accueil.data?.mises_en_avant ?? []} />
        <SectionPromotions bannieres={accueil.data?.bannieres ?? []} />
        <SectionResidencesMisesEnAvant residences={accueil.data?.residences_mises_en_avant ?? []} />
        <SectionCategories types={categories.data ?? []} />
        <SectionMeilleuresResidences residences={accueil.data?.residences_mieux_notees ?? []} />
        <SectionTemoignages temoignages={accueil.data?.temoignages ?? []} />
        <SectionApplications />
      </div>
    </div>
  )
}
