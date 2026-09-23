<?php

namespace App\Domain\Sejours\Services;

use App\Domain\Catalogue\Enums\Disponibilite;
use App\Domain\Catalogue\Enums\EtatPublication;
use App\Domain\Catalogue\Models\Logement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Moteur de recherche du site public (CdC § 5.1).
 *
 * « Seules les résidences disponibles s'affichent » : un logement sort des résultats s'il n'est
 * pas publié, si sa résidence est désactivée ou marquée « Occupée » par son propriétaire, ou si
 * un séjour ou un blocage tient une des nuits demandées. Sans dates, on cherche ce qui est libre CE SOIR.
 *
 * Tout se filtre en SQL, avec l'index GiST de la contrainte d'exclusion : l'objectif est
 * moins d'une seconde sur 500 logements (CdC § 13.2).
 */
final class RechercheDeLogements
{
    /**
     * @param  array{commune_id?: int|null, quartier_id?: int|null, type_logement_id?: int|null, occupants?: int|null,
     *               budget_max?: int|null, equipements?: list<int>|null}  $filtres
     * @return Builder<Logement>
     */
    public function requete(Carbon $arrivee, Carbon $depart, array $filtres = []): Builder
    {
        $nuits = (int) $arrivee->diffInDays($depart);

        return Logement::query()
            ->select('logements.*')
            ->where('logements.etat_publication', EtatPublication::Publie)
            ->whereHas('residence', fn (Builder $r) => $r
                ->where('active', true)
                ->where('disponibilite', Disponibilite::Disponible)
                ->when($filtres['quartier_id'] ?? null, fn (Builder $q, int $v) => $q->where('quartier_id', $v))
                ->when($filtres['commune_id'] ?? null, fn (Builder $q, int $v) => $q->whereRelation('quartier', 'commune_id', $v)))
            // Aucune occupation ne recouvre [arrivée, départ[.
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('occupations')
                ->whereColumn('occupations.logement_id', 'logements.id')
                ->whereRaw('occupations.periode && daterange(?, ?, \'[)\')', [$arrivee->toDateString(), $depart->toDateString()]))
            ->where(fn (Builder $q) => $q->whereNull('duree_minimale')->orWhere('duree_minimale', '<=', $nuits))
            ->where(fn (Builder $q) => $q->whereNull('duree_maximale')->orWhere('duree_maximale', '>=', $nuits))
            ->when($filtres['type_logement_id'] ?? null, fn (Builder $q, int $v) => $q->where('type_logement_id', $v))
            ->when($filtres['occupants'] ?? null, fn (Builder $q, int $v) => $q->where('capacite_maximale', '>=', $v))
            // Filtre sur le prix de vente du logement ; le prix exact du séjour (grille, saison) est affiché sur chaque vignette.
            ->when($filtres['budget_max'] ?? null, fn (Builder $q, int $v) => $q->where('prix_vente', '<=', $v))
            // Chaque équipement demandé doit exister, sur le logement OU sur sa résidence (piscine, parking…).
            ->when($filtres['equipements'] ?? null, function (Builder $q, array $ids): void {
                foreach ($ids as $id) {
                    $q->where(fn (Builder $ou) => $ou
                        ->whereHas('equipements', fn (Builder $e) => $e->whereKey($id))
                        ->orWhereHas('residence.equipements', fn (Builder $e) => $e->whereKey($id)));
                }
            });
    }
}
