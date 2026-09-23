import { Tabs } from 'antd'
import { useTranslation } from 'react-i18next'
import { EnTeteDePage } from '../../../shared/composants/EnTeteDePage'
import { OngletBaremeLivraisonRepas } from './OngletBaremeLivraisonRepas'
import { OngletBaremeTransfert } from './OngletBaremeTransfert'
import { OngletCodesPromo } from './OngletCodesPromo'
import { OngletGrille } from './OngletGrille'
import { OngletPourcentageEntreprise } from './OngletPourcentageEntreprise'
import { OngletPrixNegocies } from './OngletPrixNegocies'
import { OngletReferentiels } from './OngletReferentiels'

/** Back office › Tarification (CdC § 7.3, réservé administrateur). La grille est le premier
 * onglet : un lien « Voir la grille tarifaire » depuis un logement (PageDetailLogement) arrive
 * ici avec ?logement_id=X, lu directement par OngletGrille via useSearchParams. */
export function PageTarification() {
  const { t } = useTranslation()

  return (
    <div>
      <EnTeteDePage titre={t('backoffice.tarification.titre')} />
      <Tabs
        items={[
          { key: 'grille', label: t('backoffice.tarification.onglets.grille'), children: <OngletGrille /> },
          { key: 'pourcentage', label: t('backoffice.tarification.onglets.pourcentage'), children: <OngletPourcentageEntreprise /> },
          { key: 'prixNegocies', label: t('backoffice.tarification.onglets.prixNegocies'), children: <OngletPrixNegocies /> },
          { key: 'codesPromo', label: t('backoffice.tarification.onglets.codesPromo'), children: <OngletCodesPromo /> },
          { key: 'referentiels', label: t('backoffice.tarification.onglets.referentiels'), children: <OngletReferentiels /> },
          {
            key: 'baremeLivraisonRepas',
            label: t('backoffice.tarification.onglets.baremeLivraisonRepas'),
            children: <OngletBaremeLivraisonRepas />,
          },
          {
            key: 'baremeTransfert',
            label: t('backoffice.tarification.onglets.baremeTransfert'),
            children: <OngletBaremeTransfert />,
          },
        ]}
      />
    </div>
  )
}
