import { useState } from 'react'
import type { TablePaginationConfig } from 'antd'

/**
 * Pagination serveur d'une liste du back office : la page courante, le nombre de lignes
 * par page (modifiable par la personne qui consulte, 5 par défaut), et la configuration
 * prête à passer à `<Table pagination={…}>`. `reinitialiser()` doit être appelé par
 * chaque filtre de l'écran (un changement de filtre qui laisse la page sur 4 alors qu'il
 * ne reste qu'une page affiche une liste vide).
 */
export function usePagination(parPageInitial = 5) {
  const [page, setPage] = useState(1)
  const [parPage, setParPage] = useState(parPageInitial)

  return {
    page,
    parPage,
    reinitialiser: () => setPage(1),
    propsPagination: (total: number | undefined): TablePaginationConfig => ({
      current: page,
      pageSize: parPage,
      total: total ?? 0,
      showSizeChanger: true,
      pageSizeOptions: [5, 10, 20, 50, 100],
      onChange: (nouvellePage, nouvelleTaille) => {
        // Un changement de taille de page remet à la page 1 : la page 4 à 5 lignes/page
        // peut ne plus exister du tout à 50 lignes/page.
        setPage(nouvelleTaille !== parPage ? 1 : nouvellePage)
        setParPage(nouvelleTaille)
      },
    }),
  }
}
