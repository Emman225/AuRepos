<?php

namespace App\Support\Listes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Filtre « Période : du … au … » commun à TOUTES les listes et à tous les
 * exports (CdC § 6.8). Bornes incluses, journées entières, appliqué EN SQL.
 *
 * Dans Mon Gravier ce filtre était réécrit dans chaque contrôleur, et parfois
 * appliqué en PHP après avoir chargé toute la table.
 */
final class FiltrePeriode
{
    private function __construct(public readonly ?Carbon $du, public readonly ?Carbon $au) {}

    /** Lit ?du=AAAA-MM-JJ&au=AAAA-MM-JJ (le format jj/mm/aaaa est accepté aussi). */
    public static function depuis(Request $request): self
    {
        $du = self::lire($request->query('du'), 'du')?->startOfDay();
        $au = self::lire($request->query('au'), 'au')?->endOfDay();

        if ($du && $au && $du->greaterThan($au)) {
            throw ValidationException::withMessages(['au' => 'La fin de la période précède son début.']);
        }

        return new self($du, $au);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $requete
     * @return Builder<TModel>
     */
    public function appliquer(Builder $requete, string $colonne = 'created_at'): Builder
    {
        return $requete
            ->when($this->du, fn (Builder $q) => $q->where($colonne, '>=', $this->du))
            ->when($this->au, fn (Builder $q) => $q->where($colonne, '<=', $this->au));
    }

    private static function lire(mixed $valeur, string $champ): ?Carbon
    {
        if (! is_string($valeur) || trim($valeur) === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            if (Carbon::canBeCreatedFromFormat($valeur, $format)) {
                return Carbon::createFromFormat($format, $valeur);
            }
        }

        throw ValidationException::withMessages([$champ => 'Date invalide : utilisez le format jj/mm/aaaa.']);
    }
}
