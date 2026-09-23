/** GET /backoffice/tableau-de-bord (api/app/Http/Controllers/Api/V1/Backoffice/TableauDeBordController.php). */
export interface CompteursDuJour {
  reservations_en_attente: number
  arrivees_du_jour: number
  departs_du_jour: number
  sejours_en_cours: number
  devis_en_attente: number
}
