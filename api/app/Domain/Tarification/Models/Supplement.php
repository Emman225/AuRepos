<?php

namespace App\Domain\Tarification\Models;

use App\Domain\Referentiels\Models\ElementReferentiel;

/**
 * Suppléments (CdC § 7.3) : occupant au-delà de la capacité de base, week-end,
 * arrivée tardive, départ tardif.
 *
 * @property string $code
 * @property string $mode
 * @property int $montant
 * @property int|null $type_logement_id
 */
class Supplement extends ElementReferentiel
{
    protected $table = 'supplements';

    protected $fillable = ['code', 'nom', 'mode', 'montant', 'type_logement_id', 'actif'];

    /** @var array<string, mixed> */
    protected $attributes = ['actif' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['actif' => 'boolean', 'montant' => 'integer'];
    }

    protected function nature(): string
    {
        return 'supplément';
    }
}
